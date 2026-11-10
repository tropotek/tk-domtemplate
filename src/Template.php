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

    /** Tag of the placeholder a template insert leaves until the page is built */
    protected const string SLOT = 'tk-slot';

    private ?string $output = null;

    private bool $built = false;

    private ?Engine $defaultEngine = null;

    private ?Engine $inheritedEngine = null;

    /**
     * Template inserts waiting for the build: placeholder, child, and the var name to
     * re-point when the insert replaced a named node
     *
     * @var list<array{\Dom\Element, Template, ?string}>
     */
    protected array $pending = [];

    /** @var list<Asset> assets this template (or a row added to it) asked for */
    protected array $assets = [];

    /** @var list<array{\Dom\Element, Asset}> inline assets of added rows, written at the end of each row element */
    protected array $inlineAt = [];

    /**
     * Head/End assets of added rows, with the row elements they came from: kept only
     * while one of those elements is still in the page
     *
     * @var list<array{list<\Dom\Element>, Asset}>
     */
    protected array $rowAssets = [];

    /** @var list<Asset> assets handed up to the template this one is inserted into, kept for every later build */
    private array $bubbled = [];

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
     * An independent copy of an unrendered template. Child templates waiting to be
     * inserted are shared, not copied.
     *
     * @throws \LogicException when the template has been rendered
     */
    public function __clone()
    {
        if ($this->built) {
            throw new \LogicException(sprintf('Template %s has been rendered and cannot be cloned', $this->label));
        }
        if ($this->root instanceof \Dom\Document) {
            // Import into an empty document: cloneNode() on a whole HTMLDocument crashes
            // PHP 8.5 once <template> content is copied into it
            $doc = \Dom\HTMLDocument::createEmpty();
            foreach ($this->doc->childNodes as $node) {
                $doc->append($doc->importNode($node, true));
            }
            Html::copyTemplates($this->doc, $doc);
            $old = iterator_to_array($this->doc->getElementsByTagName('*'), false);
            $new = iterator_to_array($doc->getElementsByTagName('*'), false);
            $this->doc = $doc;
            $this->root = $doc;
        } else {
            $root = Html::cloneDeep($this->root);
            $old = [$this->root, ...iterator_to_array($this->root->getElementsByTagName('*'), false)];
            $new = [$root, ...iterator_to_array($root->getElementsByTagName('*'), false)];
            $this->root = $root;
        }
        /** @var \WeakMap<\Dom\Element, \Dom\Element> $map */
        $map = new \WeakMap();
        foreach ($old as $i => $el) {
            $map[$el] = $new[$i];
        }
        $this->scope = $this->scope->remap($map);
        $pending = [];
        foreach ($this->pending as [$slot, $child, $replaces]) {
            if (isset($map[$slot])) {
                $pending[] = [$map[$slot], $child, $replaces];
            }
        }
        $this->pending = $pending;
        $inlineAt = [];
        foreach ($this->inlineAt as [$node, $asset]) {
            if (isset($map[$node])) {
                $inlineAt[] = [$map[$node], $asset];
            }
        }
        $this->inlineAt = $inlineAt;
        $rowAssets = [];
        foreach ($this->rowAssets as [$nodes, $asset]) {
            $rowAssets[] = [array_values(array_filter(array_map(fn($n) => $map[$n] ?? null, $nodes))), $asset];
        }
        $this->rowAssets = $rowAssets;
        /** @var \WeakMap<\Dom\Element, \Dom\Element> $firstRows */
        $firstRows = new \WeakMap();
        foreach ($this->firstRows as $sample => $first) {
            if (isset($map[$sample], $map[$first])) {
                $firstRows[$map[$sample]] = $map[$first];
            }
        }
        $this->firstRows = $firstRows;
        $this->output = null;
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

    /**
     * A fragment template from a copy of an element in another document.
     * The node is copied, never re-parsed, so a bare <tr> or <option> keeps its structure.
     */
    public static function fromNode(\Dom\Element $node, ?Engine $engine = null, string $label = '(node)'): static
    {
        $doc = \Dom\HTMLDocument::createEmpty();
        $root = $doc->createElement(Html::contextFor($node->tagName));
        $root->append(Html::import($doc, $node));
        return new static($doc, $root, $label, $engine); // @phpstan-ignore new.static (Row blocks it)
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
     * Add JavaScript. Inline (the default) writes it at the end of this template.
     *
     * @param array<string, string|bool|null> $attrs
     * @param ?string $key assets with the same key are written once per page
     */
    public function js(string $code, Place $place = Place::Inline, ?string $key = null, array $attrs = []): static
    {
        return $this->addAsset(new Asset('js', $code, $place, $key, $attrs));
    }

    /**
     * Add CSS. Inline (the default) writes it at the end of this template.
     *
     * @param array<string, string|bool|null> $attrs
     */
    public function css(string $code, Place $place = Place::Inline, ?string $key = null, array $attrs = []): static
    {
        return $this->addAsset(new Asset('css', $code, $place, $key, $attrs));
    }

    /**
     * Add a script URL to the page <head>, once per page
     *
     * @param array<string, string|bool|null> $attrs
     */
    public function jsUrl(string $url, array $attrs = []): static
    {
        return $this->addAsset(new Asset('jsUrl', $url, Place::Head, null, $attrs));
    }

    /**
     * Add a stylesheet URL to the page <head>, once per page
     *
     * @param array<string, string|bool|null> $attrs
     */
    public function cssUrl(string $url, array $attrs = []): static
    {
        return $this->addAsset(new Asset('cssUrl', $url, Place::Head, null, $attrs));
    }

    /**
     * Set a <meta name> tag in the page <head>; the last value set wins
     */
    public function meta(string $name, string $content): static
    {
        return $this->addAsset(new Asset('meta', $content, Place::Head, null, [], $name));
    }

    /**
     * Set the page <title>. Ignored in a fragment.
     */
    public function title(string $title): static
    {
        return $this->addAsset(new Asset('title', $title));
    }

    private function addAsset(Asset $asset): static
    {
        $this->assertMutable();
        $this->assets[] = new Asset($asset->type, $asset->value, $asset->place, $asset->key, $asset->attrs, $asset->name, hrtime(true));
        return $this;
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
     * Called when one of this template's rows is added: take over its pending inserts and assets
     *
     * @param list<\Dom\Element> $nodes the row elements that were inserted (none when hidden)
     */
    protected function adopt(Row $row, array $nodes): void
    {
        foreach ($row->pending as [$slot, $child]) {
            $this->pending[] = [$slot, $child, null];
        }
        $row->pending = [];
        if ($nodes) {
            foreach ($row->assets as $asset) {
                if ($asset->type !== 'title' && $asset->place === Place::Inline) {
                    foreach ($nodes as $node) {
                        $this->inlineAt[] = [$node, $asset];
                    }
                } else {
                    $this->rowAssets[] = [$nodes, $asset];
                }
            }
            array_push($this->inlineAt, ...$row->inlineAt);
            array_push($this->rowAssets, ...$row->rowAssets);
        }
        $row->assets = [];
        $row->inlineAt = [];
        $row->rowAssets = [];
    }

    /**
     * A placeholder for a template insert, resolved when the page is built
     *
     * @internal used by Handle
     */
    public function slot(Template $child, ?string $replaces = null): \Dom\Element
    {
        // The child uses this template's engine from now on, unless it has its own
        if ($child->engine === null && $child->inheritedEngine === null && $child !== $this) {
            $child->inheritedEngine = $this->engine();
        }
        $slot = $this->doc->createElement(self::SLOT);
        $this->pending[] = [$slot, $child, $replaces];
        return $slot;
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

    public function html(string $name, string|Template $html): static
    {
        $this->var($name)->html($html);
        return $this;
    }

    public function append(string $name, string|Template $html): static
    {
        $this->var($name)->append($html);
        return $this;
    }

    public function prepend(string $name, string|Template $html): static
    {
        $this->var($name)->prepend($html);
        return $this;
    }

    public function replace(string $name, string|Template $html): static
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
            $ctx = new BuildContext();
            $this->build($ctx);
            $this->placeAssets($this->bubbled, $ctx);
            $this->dedupeKeys();
            $this->engine()->modifier?->process(new Modifier\Context($this->doc, $this->root, $this->isFragment()));
            $this->applyNonce();
            $this->output = $this->root instanceof \Dom\Document ? $this->doc->saveHtml() : $this->root->innerHTML;
        }
        return $this->output;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * True once the template has been built, by render() or as part of the page it was inserted into
     */
    public function isRendered(): bool
    {
        return $this->built;
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
     * The engine this template uses: its own, else that of the template it was inserted
     * into (from the moment it is inserted), else the strict default
     */
    public function engine(): Engine
    {
        return $this->engine ?? $this->inheritedEngine ?? ($this->defaultEngine ??= new Engine());
    }

    /**
     * Build the tree once: resolve template inserts, then clean up. Locks the template.
     */
    protected function build(BuildContext $ctx): void
    {
        if ($this->built) {
            return;
        }
        $ctx->enter($this);
        try {
            // Hidden content first, so templates inside it are never built
            $this->removeHidden();
            $collected = $this->resolvePending($ctx);
            $this->writeInline($ctx);
            $fromRows = [];
            foreach ($this->rowAssets as [$nodes, $asset]) {
                foreach ($nodes as $node) {
                    if ($this->root->contains($node)) {
                        $fromRows[] = $asset;
                        break;
                    }
                }
            }
            $this->rowAssets = [];
            $this->bubbled = [
                ...array_values(array_filter($this->assets, fn(Asset $a) => $a->type === 'title' || $a->place !== Place::Inline)),
                ...$fromRows,
                ...$collected,
            ];
            $this->stripMarkers(true);
            $this->built = true;
        } finally {
            $ctx->leave();
        }
    }

    /**
     * Replace each placeholder with the built nodes of its child template.
     * Returns the assets the children handed up.
     *
     * @return list<Asset>
     */
    private function resolvePending(BuildContext $ctx): array
    {
        $collected = [];
        foreach ($this->pending as [$slot, $child, $replaces]) {
            if (!$this->root->contains($slot)) {
                continue;
            }
            if ($child->engine === null && $child->inheritedEngine === null) {
                $child->inheritedEngine = $this->engine();
            }
            $child->build($ctx);
            array_push($collected, ...$child->bubbled);
            $nodes = array_map(fn(\Dom\Node $n) => Html::import($this->doc, $n), $child->contentNodes());
            self::carryAttributes($slot, $nodes);
            $slot->replaceWith(...$nodes);
            if ($replaces !== null) {
                $this->scope->replaceNode($replaces, $slot, array_values(array_filter($nodes, fn($n) => $n instanceof \Dom\Element)));
            }
        }
        $this->pending = [];
        return $collected;
    }

    /**
     * Write this template's own inline assets at its end, and added rows' inline assets
     * at the end of each row. Keyed assets are marked and reduced to one per page at render.
     */
    private function writeInline(BuildContext $ctx): void
    {
        $end = $this->endNode();
        foreach ($this->assets as $asset) {
            if ($asset->type !== 'title' && $asset->place === Place::Inline) {
                $end?->append($asset->toElement($this->doc));
            }
        }
        foreach ($this->inlineAt as [$node, $asset]) {
            if ($this->root->contains($node)) {
                $node->append($asset->toElement($this->doc));
            }
        }
        $this->inlineAt = [];
    }

    /**
     * Where inline content goes: the end of a fragment, or of a full page's <body>
     */
    private function endNode(): \Dom\Element|null
    {
        return $this->root instanceof \Dom\Document ? ($this->doc->body ?? $this->doc->documentElement) : $this->root;
    }

    /**
     * Place the assets handed up to the top level, in call order: <head>, end of <body>, meta and title.
     * Without a <head> or <body> (a fragment) they are written inline and the title is dropped.
     *
     * @param list<Asset> $assets
     */
    private function placeAssets(array $assets, BuildContext $ctx): void
    {
        $head = $this->root instanceof \Dom\Document ? $this->doc->head : null;
        $body = $this->root instanceof \Dom\Document ? $this->doc->body : null;
        $end = $this->endNode();
        // URLs the page already links count as written
        foreach ($this->root->querySelectorAll('link[rel="stylesheet"][href]') as $link) {
            $ctx->first('cssUrl:' . $link->getAttribute('href'));
        }
        foreach ($this->root->querySelectorAll('script[src]') as $script) {
            $ctx->first('jsUrl:' . $script->getAttribute('src'));
        }
        usort($assets, fn(Asset $a, Asset $b) => $a->seq <=> $b->seq);
        foreach ($assets as $asset) {
            if (in_array($asset->type, ['jsUrl', 'cssUrl'], true) && !$ctx->first($asset->type . ':' . $asset->value)) {
                continue;
            }
            if ($asset->type === 'title') {
                if ($head !== null) {
                    $title = $head->querySelector('title');
                    $title ? $title->textContent = $asset->value : $head->append($asset->toElement($this->doc));
                }
                continue;
            }
            $el = $asset->toElement($this->doc);
            if ($asset->type === 'meta' && $head !== null) {
                $existing = null;
                foreach ($head->querySelectorAll('meta[name]') as $meta) {
                    if ($meta->getAttribute('name') === $asset->name) {
                        $existing = $meta;
                        break;
                    }
                }
                $existing ? $existing->replaceWith($el) : $head->append($el);
                continue;
            }
            $target = $asset->place === Place::End ? $body : $head;
            ($target ?? $end)?->append($el);
        }
    }

    /**
     * Keep the first asset of each key in the page and drop the rest
     */
    private function dedupeKeys(): void
    {
        $seen = [];
        foreach ($this->root->querySelectorAll('[' . Asset::KEY_ATTR . ']') as $el) {
            $key = (string)$el->getAttribute(Asset::KEY_ATTR);
            if (isset($seen[$key])) {
                $el->remove();
                continue;
            }
            $seen[$key] = true;
            $el->removeAttribute(Asset::KEY_ATTR);
        }
    }

    /**
     * Add the engine's CSP nonce to every inline <script> and <style> that lacks one
     */
    private function applyNonce(): void
    {
        $nonce = $this->engine()->nonce;
        if ($nonce === null) {
            return;
        }
        foreach ($this->root->querySelectorAll('script:not([src]):not([nonce]), style:not([nonce])') as $el) {
            $el->setAttribute('nonce', $nonce);
        }
    }

    /**
     * Attributes and classes set on a placeholder (after replace() with a template)
     * are applied to the replacement's top-level elements
     *
     * @param list<\Dom\Node> $nodes
     */
    private static function carryAttributes(\Dom\Element $slot, array $nodes): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof \Dom\Element) {
                continue;
            }
            foreach ($slot->attributes as $attr) {
                if ($attr->name === 'class') {
                    $tokens = preg_split('/\s+/', trim($attr->value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if ($tokens) {
                        $node->classList->add(...$tokens);
                    }
                } else {
                    $copy = $attr->cloneNode();
                    assert($copy instanceof \Dom\Attr);
                    $node->setAttributeNode($copy);
                }
            }
        }
    }

    /**
     * The nodes a parent imports: a fragment's top-level nodes, or a full page's <body> children
     *
     * @return list<\Dom\Node>
     */
    private function contentNodes(): array
    {
        $parent = $this->root instanceof \Dom\Document ? ($this->doc->body ?? $this->doc->documentElement) : $this->root;
        return $parent ? iterator_to_array($parent->childNodes, false) : [];
    }

    /**
     * Finish the tree: remove hidden nodes and repeat samples, then strip the markers.
     * Rows keep their placeholders ($dropSlots false); the page resolves them.
     */
    protected function cleanup(bool $dropSlots = true): void
    {
        $this->removeHidden();
        $this->stripMarkers($dropSlots);
    }

    /**
     * Remove hidden nodes, repeat samples and rejected optional/repeat nodes
     */
    private function removeHidden(): void
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
    }

    /**
     * Strip var attributes and, at the top level, any placeholder that was never resolved
     */
    private function stripMarkers(bool $dropSlots): void
    {
        if ($dropSlots) {
            foreach ($this->root->querySelectorAll(self::SLOT) as $slot) {
                $slot->remove();
            }
        }
        foreach ($this->root->querySelectorAll('[var]') as $el) {
            $el->removeAttribute('var');
        }
    }
}
