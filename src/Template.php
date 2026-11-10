<?php
namespace Tk\DomTemplate;

/**
 * An HTML template: a full page or a fragment, with var nodes PHP can change.
 *
 * A template renders once. After render() it is locked and further changes throw.
 *
 * @phpstan-consistent-constructor
 */
class Template
{
    protected Scope $scope;

    private ?string $output = null;

    private ?Engine $defaultEngine = null;

    protected function __construct(
        protected \Dom\HTMLDocument $doc,
        protected \Dom\Element|\Dom\Document $root,
        private readonly string $label,
        private readonly ?Engine $engine,
    ) {
        $this->scope = Scope::build($root, $label, fn(string $message) => $this->engine()->fail($message));
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
            return new static($doc, $doc, $label, $engine);
        }
        $doc = \Dom\HTMLDocument::createEmpty();
        return new static($doc, Html::parseFragment($doc, $html), $label, $engine);
    }

    /**
     * A handle on every node with this var name
     */
    public function var(string $name): Handle
    {
        return new Handle($this, $name, $this->scope->nodes($name));
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
     * Called before every change
     *
     * @internal
     */
    public function assertMutable(): void
    {
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
                    $node->remove();
                }
            }
        }
        foreach ($this->scope->rejected() as $node) {
            $node->remove();
        }
        foreach ($this->root->querySelectorAll('[var]') as $el) {
            $el->removeAttribute('var');
        }
    }
}
