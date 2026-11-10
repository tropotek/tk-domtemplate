<?php
namespace Tk\DomTemplate;

/**
 * An HTML template: a full page or a fragment, with var nodes PHP can change.
 *
 * A template renders once. After render() it is locked and further changes throw.
 */
class Template
{
    protected Scope $scope;

    private ?string $output = null;

    private ?Engine $defaultEngine = null;

    /** @var \WeakMap<\Dom\Element, \Dom\Element> the first row added from each sample */
    private \WeakMap $firstRows;

    protected function __construct(
        protected \Dom\HTMLDocument $doc,
        protected \Dom\Element|\Dom\Document $root,
        private readonly string $label,
        private readonly ?Engine $engine,
    ) {
        $this->firstRows = new \WeakMap();
        $this->scope = Scope::build($root, $label, fn(string $message) => $this->reportMarkup($message));
    }

    /**
     * Load a template from a string of HTML, a full page or a fragment
     */
    public static function load(string $html, ?Engine $engine = null): static
    {
        return static::parse($html, '(string)', $engine);
    }

    /**
     * Load a template from a file. The path is used in error messages.
     *
     * @throws TemplateException when the file can't be read
     */
    public static function file(string $path, ?Engine $engine = null): static
    {
        $html = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($html === false) {
            throw new TemplateException('Cannot read template file: ' . $path);
        }
        return static::parse($html, $path, $engine);
    }

    protected static function parse(string $html, string $label, ?Engine $engine): static
    {
        if (str_starts_with($html, "\xEF\xBB\xBF")) {
            $html = substr($html, 3);
        }
        if (Html::isFullDocument($html)) {
            $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
            return new static($doc, $doc, $label, $engine); // @phpstan-ignore new.static (Row blocks load()/file())
        }
        $doc = \Dom\HTMLDocument::createEmpty();
        return new static($doc, Html::parseFragment($doc, $html), $label, $engine); // @phpstan-ignore new.static
    }

    /**
     * Report a markup mistake found while indexing
     */
    protected function reportMarkup(string $message): void
    {
        $this->engine()->fail($message);
    }

    /**
     * A handle on every node with this var name
     */
    public function var(string $name): Handle
    {
        $kind = $this->scope->kind($name);
        if ($kind === null) {
            $this->engine()->fail(sprintf('No var `%s` in %s.%s', $name, $this->label, $this->suggest($name)));
            return new Handle($this, $name, []);
        }
        if ($kind === Kind::Repeat) {
            $this->engine()->fail(sprintf('`%s` is a repeat in %s, use repeat()', $name, $this->label));
            return new Handle($this, $name, []);
        }
        return new Handle($this, $name, $this->scope->nodes($name));
    }

    /**
     * A new row from a repeat sample. Fill it in, then add it with append() or appendTo().
     */
    public function repeat(string $name): Row
    {
        $kind = $this->scope->kind($name);
        $sample = $this->scope->sample($name);
        if ($kind === null) {
            $this->engine()->fail(sprintf('No repeat `%s` in %s.%s', $name, $this->label, $this->suggest($name)));
        } elseif ($sample === null) {
            $this->engine()->fail(sprintf('`%s` is not a repeat in %s, use var()', $name, $this->label));
        }
        // In lenient mode a detached stand-in sample gives a row that is never inserted
        return new Row($this, $sample ?? $this->doc->createElement('template'), $name);
    }

    /**
     * Add one row per item. The callback fills the row in; a row the callback didn't
     * add itself (with appendTo() for example) is then appended in place.
     *
     * @param iterable<mixed> $items
     * @param callable $fn called as $fn(Row $row, mixed $item, int|string $key)
     */
    public function each(string $name, iterable $items, callable $fn): static
    {
        foreach ($items as $key => $item) {
            $row = $this->repeat($name);
            $fn($row, $item, $key);
            if (!$row->isAdded()) {
                $row->append();
            }
        }
        return $this;
    }

    /**
     * The template a row belongs to; null for a top-level template
     */
    protected function parent(): ?Template
    {
        return null;
    }

    /**
     * Called when one of this template's rows is added
     */
    protected function adopt(Row $row): void
    {
    }

    /**
     * Insert a finished row: before the sample, or before the first row from it when prepending
     *
     * @internal used by Row
     */
    public function placeRow(\Dom\Element $sample, \Dom\Element $node, bool $prepend): void
    {
        $first = $this->firstRows[$sample] ?? null;
        if ($prepend && $first !== null) {
            $first->before($node);
        } else {
            $sample->before($node);
        }
        if ($prepend || $first === null) {
            $this->firstRows[$sample] = $node;
        }
    }

    /**
     * " Did you mean `x`?" for the closest known name within 2 edits, or ''
     */
    private function suggest(string $name): string
    {
        $best = null;
        $bestDistance = 3;
        foreach ($this->scope->names() as $known) {
            $distance = levenshtein($name, $known);
            if ($distance < $bestDistance) {
                $best = $known;
                $bestDistance = $distance;
            }
        }
        return $best === null ? '' : sprintf(' Did you mean `%s`?', $best);
    }

    /**
     * True when the template has a node with this name, of any kind
     */
    public function has(string $name): bool
    {
        return $this->scope->kind($name) !== null;
    }

    public function text(string $name, string $text): static
    {
        $this->var($name)->text($text);
        return $this;
    }

    public function html(string $name, string $html): static
    {
        $this->var($name)->html($html);
        return $this;
    }

    public function append(string $name, string $html): static
    {
        $this->var($name)->append($html);
        return $this;
    }

    public function prepend(string $name, string $html): static
    {
        $this->var($name)->prepend($html);
        return $this;
    }

    public function replace(string $name, string $html): static
    {
        $this->var($name)->replaceWith($html);
        return $this;
    }

    /**
     * @param string|array<string, string|int|float|bool|null> $attr
     */
    public function attr(string $name, string|array $attr, string|int|float|bool|null $value = null): static
    {
        $this->var($name)->attr($attr, $value);
        return $this;
    }

    /**
     * @param string|list<string> $class
     */
    public function addClass(string $name, string|array $class): static
    {
        $this->var($name)->addClass($class);
        return $this;
    }

    /**
     * @param string|list<string> $class
     */
    public function removeClass(string $name, string|array $class): static
    {
        $this->var($name)->removeClass($class);
        return $this;
    }

    /**
     * Keep the nodes in the output (true) or remove them at render (false)
     */
    public function show(string $name, bool $visible = true): static
    {
        $this->var($name)->show($visible);
        return $this;
    }

    /**
     * Remove the nodes at render
     */
    public function hide(string $name): static
    {
        $this->var($name)->hide();
        return $this;
    }

    /**
     * @internal used by Handle
     */
    public function setVisible(string $name, bool $visible): void
    {
        $this->scope->setVisible($name, $visible);
    }

    /**
     * @internal used by Handle after replaceWith()
     *
     * @param list<\Dom\Element> $new
     */
    public function replaceNode(string $name, \Dom\Element $old, array $new): void
    {
        $this->scope->replaceNode($name, $old, $new);
    }

    /**
     * Called before every change: a rendered template is locked
     *
     * @throws \LogicException when the template has been rendered
     *
     * @internal
     */
    public function assertMutable(): void
    {
        if ($this->isRendered()) {
            throw new \LogicException(sprintf('Template %s has already been rendered', $this->label));
        }
    }

    /**
     * Render the template. The first call builds and locks it; later calls return the same string.
     */
    public function render(): string
    {
        if ($this->output === null) {
            $this->build();
            $this->output = $this->root instanceof \Dom\Document ? $this->doc->saveHtml() : $this->root->innerHTML;
        }
        return $this->output;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function isRendered(): bool
    {
        return $this->output !== null;
    }

    public function isFragment(): bool
    {
        return !($this->root instanceof \Dom\Document);
    }

    /**
     * The file path the template came from, or "(string)"
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * The engine this template uses: its own, or the strict default
     */
    public function engine(): Engine
    {
        return $this->engine ?? ($this->defaultEngine ??= new Engine());
    }

    /**
     * Finish the tree: remove hidden nodes and repeat samples, then strip the var markers
     */
    protected function build(): void
    {
        foreach ($this->scope->names() as $name) {
            if ($this->scope->kind($name) === Kind::Repeat || !$this->scope->isVisible($name)) {
                foreach ($this->scope->nodes($name) as $node) {
                    if ($node->parentNode !== null) {
                        $node->remove();
                    }
                }
            }
        }
        foreach ($this->scope->rejected() as $node) {
            if ($node->parentNode !== null) {
                $node->remove();
            }
        }
        foreach ($this->root->querySelectorAll('[var]') as $el) {
            $el->removeAttribute('var');
        }
    }
}
