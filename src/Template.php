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
    private const string SLOT = 'tk-slot';

    private ?string $output = null;

    private bool $built = false;

    /** Set once a render has started changing the tree, so a failed render isn't re-run */
    private bool $rendering = false;

    /** @var ?\WeakMap<\Dom\Element, true> placeholders made by slot(), so user-written <tk-slot> elements are left alone */
    private static ?\WeakMap $slots = null;

    private ?Engine $defaultEngine = null;

    /**
     * The template this one is inserted into: its engine is used when this one has none.
     * Held weakly, so a child reused across requests doesn't keep an old page alive.
     *
     * @var ?\WeakReference<Template>
     */
    private ?\WeakReference $insertedInto = null;

    private ?HandleOps $ops = null;

    /**
     * Template inserts waiting for the build: placeholder, child, and the var name to
     * re-point when the insert replaced a named node
     *
     * @var list<array{\Dom\Element, Template, ?string}>
     */
    protected array $pending = [];

    protected Assets $assets;

    /** @var \WeakMap<\Dom\Element, \Dom\Element> the first row added from each sample */
    private \WeakMap $firstRows;

    protected function __construct(
        protected \Dom\HTMLDocument $doc,
        protected \Dom\Element|\Dom\Document $root,
        private readonly string $label,
        private readonly ?Engine $engine,
    ) {
        $this->firstRows = new \WeakMap();
        $this->assets = new Assets();
        $this->scope = Scope::build($root, $label, fn(string $message) => $this->reportMarkup($message), $this->checksMarkup());
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
            $doc = Html::copyDocument($this->doc);
            $map = Html::mapElements($this->doc, $doc);
            $this->doc = $doc;
            $this->root = $doc;
        } else {
            $root = Html::cloneDeep($this->root);
            $map = Html::mapElements($this->root, $root);
            $this->root = $root;
        }
        self::copySlotMarks($map);
        $this->ops = null;
        $this->scope = $this->scope->remap($map);
        $pending = [];
        foreach ($this->pending as [$slot, $child, $replaces]) {
            if (isset($map[$slot])) {
                $pending[] = [$map[$slot], $child, $replaces];
            }
        }
        $this->pending = $pending;
        $this->assets = $this->assets->remap($map);
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
        return static::parse(Html::readFile($path), $path, $engine);
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
        Html::trustSource($root);
        return new static($doc, $root, $label, $engine); // @phpstan-ignore new.static (Row blocks it)
    }

    protected static function parse(string $html, string $label, ?Engine $engine): static
    {
        [$doc, $root] = Html::parse($html);
        Html::trustSource($root);
        $template = new static($doc, $root, $label, $engine); // @phpstan-ignore new.static (Row blocks load()/file())
        Html::checkCharset($root, $label, fn(string $message) => $template->reportMarkup($message));
        return $template;
    }

    /**
     * Whether indexing runs the checks that only report (repeat samples, <template> content)
     */
    protected function checksMarkup(): bool
    {
        return true;
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
            return new Handle($this, $name, [], $this->ops());
        }
        if ($kind === Kind::Repeat) {
            $this->engine()->fail(sprintf('`%s` is a repeat in %s, use repeat()', $name, $this->label));
            return new Handle($this, $name, [], $this->ops());
        }
        return new Handle($this, $name, $this->liveNodes($name, $this->scope->nodes($name)), $this->ops());
    }

    /**
     * The nodes still in the tree. Nodes inside content replaced with html() or
     * replace() are gone, so changing them is reported instead of doing nothing.
     *
     * @param list<\Dom\Element> $nodes
     * @return list<\Dom\Element>
     */
    private function liveNodes(string $name, array $nodes): array
    {
        if ($this->isFinished()) {
            return $nodes;
        }
        $live = array_values(array_filter($nodes, fn(\Dom\Element $n) => $this->root->contains($n)));
        if (count($live) < count($nodes)) {
            $this->engine()->fail(sprintf('`%s` in %s was inside content that has been replaced', $name, $this->label));
        }
        return $live;
    }

    /**
     * True once the tree is final: hidden nodes may already have been removed
     */
    protected function isFinished(): bool
    {
        return $this->built;
    }

    /**
     * Add JavaScript. Inline (the default) writes it at the end of this template.
     *
     * @param array<int|string, string|bool|null> $attrs name => value, or a bare name ('defer')
     * @param ?string $key assets with the same key are written once per page
     */
    public function js(string $code, Place $place = Place::Inline, ?string $key = null, array $attrs = []): static
    {
        return $this->addAsset(AssetType::Js, $code, $place, $key, $attrs);
    }

    /**
     * Add CSS. Inline (the default) writes it at the end of this template.
     *
     * @param array<int|string, string|bool|null> $attrs
     */
    public function css(string $code, Place $place = Place::Inline, ?string $key = null, array $attrs = []): static
    {
        return $this->addAsset(AssetType::Css, $code, $place, $key, $attrs);
    }

    /**
     * Add a script URL to the page <head>, once per page
     *
     * @param array<int|string, string|bool|null> $attrs
     */
    public function jsUrl(string $url, array $attrs = []): static
    {
        return $this->addAsset(AssetType::JsUrl, $url, Place::Head, null, $attrs);
    }

    /**
     * Add a stylesheet URL to the page <head>, once per page
     *
     * @param array<int|string, string|bool|null> $attrs
     */
    public function cssUrl(string $url, array $attrs = []): static
    {
        return $this->addAsset(AssetType::CssUrl, $url, Place::Head, null, $attrs);
    }

    /**
     * Set a <meta> tag in the page <head>; the last value set wins. $attr names the tag
     * by name (the default), property (Open Graph), http-equiv or itemprop.
     *
     * @throws TemplateException for any other $attr
     */
    public function meta(string $name, string $content, string $attr = 'name'): static
    {
        if (!in_array($attr, ['name', 'property', 'http-equiv', 'itemprop'], true)) {
            throw new TemplateException('meta() attr must be name, property, http-equiv or itemprop');
        }
        return $this->addAsset(AssetType::Meta, $content, Place::Head, null, [], $name, $attr);
    }

    /**
     * Set the page <title>. Ignored in a fragment.
     */
    public function title(string $title): static
    {
        return $this->addAsset(AssetType::Title, $title);
    }

    /**
     * @param array<int|string, string|bool|null> $attrs
     * @throws TemplateException when an attribute name is invalid
     */
    private function addAsset(AssetType $type, string $value, Place $place = Place::Head, ?string $key = null, array $attrs = [], ?string $name = null, string $metaAttr = 'name'): static
    {
        $this->assertMutable();
        $named = [];
        foreach ($attrs as $attr => $val) {
            if (is_int($attr)) {
                if (!is_string($val)) {
                    throw new TemplateException(sprintf('Invalid attribute `%s` for %s() in %s: a list entry must be an attribute name', var_export($val, true), $type->value, $this->label));
                }
                [$attr, $val] = [$val, true];
            }
            if (!Html::isAttrName($attr)) {
                throw new TemplateException(sprintf('Invalid attribute name `%s` for %s() in %s', $attr, $type->value, $this->label));
            }
            $named[$attr] = $val;
        }
        $named = array_map(fn($v) => is_string($v) ? Html::utf8($v) : $v, $named);
        $name = $name === null ? null : Html::utf8($name);
        $this->assets->add(new Asset($type, Html::utf8($value), $place, $key === null ? null : Html::utf8($key), $named, $name, hrtime(true), $metaAttr));
        return $this;
    }

    /**
     * A new row from a repeat sample. Fill it in, then insert it with insert() or insertInto().
     */
    public function repeat(string $name): Row
    {
        $kind = $this->scope->kind($name);
        $sample = $this->scope->sample($name);
        if ($kind === null) {
            $this->engine()->fail(sprintf('No repeat `%s` in %s.%s', $name, $this->label, $this->suggest($name)));
        } elseif ($sample === null) {
            $this->engine()->fail(sprintf('`%s` is not a repeat in %s, use var()', $name, $this->label));
        } elseif (!$this->liveNodes($name, [$sample])) {
            $sample = null;
        }
        // In lenient mode a detached stand-in sample gives a row that is never inserted
        return new Row($this, $sample ?? $this->doc->createElement('template'), $name);
    }

    /**
     * Add one row per item. The callback fills the row in; a row the callback didn't
     * add itself (with insertInto() for example) is then appended in place.
     *
     * @param iterable<mixed> $items
     * @param callable $fn called as $fn(Row $row, mixed $item, int|string $key)
     */
    public function each(string $name, iterable $items, callable $fn): static
    {
        foreach ($items as $key => $item) {
            $row = $this->repeat($name);
            $fn($row, $item, $key);
            if (!$row->isInserted()) {
                $row->insert();
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
        $this->assets->adopt($row->assets, $nodes);
    }

    /**
     * A placeholder for a template insert, resolved when the page is built
     *
     * Reached by Handle only (see HandleOps)
     */
    private function slot(Template $child, ?string $replaces = null): \Dom\Element
    {
        if ($child instanceof Row) {
            throw new \LogicException('A row cannot be inserted as a template; insert it with insert()');
        }
        $child->assertInsertable();
        $this->inherit($child);
        $slot = $this->doc->createElement(self::SLOT);
        self::$slots ??= new \WeakMap();
        self::$slots[$slot] = true;
        $this->pending[] = [$slot, $child, $replaces];
        return $slot;
    }

    /**
     * @throws \LogicException when the template was rendered on its own: its assets and
     *     modifiers are already in its output and would be applied again
     */
    private function assertInsertable(): void
    {
        if ($this->output !== null || ($this->rendering && !$this->built)) {
            throw new \LogicException(sprintf('Template %s was rendered on its own; insert it before rendering', $this->label));
        }
    }

    /**
     * Placeholders copied along with a tree are placeholders too
     *
     * @param \WeakMap<\Dom\Element, \Dom\Element> $map original element => copy
     */
    protected static function copySlotMarks(\WeakMap $map): void
    {
        if (self::$slots === null) {
            return;
        }
        foreach ($map as $old => $new) {
            if (isset(self::$slots[$old])) {
                self::$slots[$new] = true;
            }
        }
    }

    /**
     * Insert a finished row: before the sample, or before the first row from it when prepending
     *
     */
    protected function placeRow(\Dom\Element $sample, \Dom\Element $node, bool $prepend): void
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
     * " Did you mean `x`?" for the closest known name, or ''. Names of 1-2 characters get
     * no suggestion, 3-4 characters allow 1 edit, longer names 2.
     */
    private function suggest(string $name): string
    {
        $length = strlen($name);
        $limit = $length <= 2 ? 0 : ($length <= 4 ? 1 : 2);
        if ($limit === 0) {
            return '';
        }
        $best = null;
        $bestDistance = $limit + 1;
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
        $this->var($name)->replace($html);
        return $this;
    }

    /**
     * Set one attribute, or several from an array. With no value, a bare attribute
     * (disabled="") is set; false and null remove it.
     *
     * @param string|array<string, string|int|float|bool|null> $attr
     */
    public function attr(string $name, string|array $attr, string|int|float|bool|null $value = null): static
    {
        $this->var($name)->attr($attr, func_num_args() < 3 ? true : $value);
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
     * Reached by Handle only
     */
    private function setVisible(string $name, bool $visible): void
    {
        $this->scope->setVisible($name, $visible);
    }

    /**
     * Reached by Handle only, after replace()
     *
     * @param list<\Dom\Element> $new
     */
    private function replaceNode(string $name, \Dom\Element $old, array $new): void
    {
        $this->scope->replaceNode($name, $old, $new);
    }

    /**
     * Called before every change: a rendered template is locked
     *
     * @throws \LogicException when the template has been rendered
     */
    protected function assertMutable(): void
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
            if ($this->rendering) {
                throw new \LogicException(sprintf('Template %s failed to render earlier; load it again', $this->label));
            }
            // Nothing is changed until every insert is known to be buildable
            /** @var \WeakMap<Template, true> $checked */
            $checked = new \WeakMap();
            $this->checkInserts([], $checked);
            $this->rendering = true;
            $ctx = new BuildContext();
            $this->build($ctx);
            Assets::place($this->assets->bubbled(), $this->doc, $this->root, $this->endNode(), $ctx);
            Assets::dedupeKeys($this->root);
            // Trust markers are taken out before modifiers run, so they never see the secret
            $trusted = Assets::takeTrusted($this->root);
            $this->engine()->modifiers?->process(new Modifier\Context($this->doc, $this->root, $this->isFragment(), $trusted));
            Assets::applyNonce($this->root, $trusted, $this->engine()->currentNonce());
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
        return $this->ownEngine() ?? ($this->defaultEngine ??= new Engine());
    }

    /**
     * Its own engine, or that of the templates it is inserted into, without the default
     */
    private function ownEngine(): ?Engine
    {
        return $this->engine ?? $this->insertedInto?->get()?->ownEngine();
    }

    /**
     * The callables Handle uses to reach this template's private helpers
     */
    private function ops(): HandleOps
    {
        return $this->ops ??= new HandleOps(
            $this->assertMutable(...),
            $this->slot(...),
            $this->replaceNode(...),
            $this->setVisible(...),
            $this->pruneDetached(...),
            self::isSlot(...),
        );
    }

    /**
     * A child without its own engine uses this template's, looked up when needed, so
     * inserting this template into a page later reaches the child too. Skipped when it
     * would make a loop (the child already holds this template, directly or not).
     */
    private function inherit(Template $child): void
    {
        // Keep the current parent unless it leads to no engine (an engine-less wrapper)
        if ($child->engine !== null || ($child->insertedInto?->get() !== null && $child->ownEngine() !== null)) {
            return;
        }
        for ($t = $this; $t !== null; $t = $t->insertedInto?->get()) {
            if ($t === $child) {
                return;
            }
        }
        $child->insertedInto = \WeakReference::create($this);
    }

    /**
     * True for a placeholder left by a template insert
     *
     * Reached by Handle only
     */
    private static function isSlot(\Dom\Element $el): bool
    {
        return self::$slots !== null && isset(self::$slots[$el]);
    }

    /**
     * Forget this name's nodes that are no longer in the tree: a change to the name
     * overwrote its own nested nodes of the same name
     *
     * Reached by Handle only
     */
    private function pruneDetached(string $name): void
    {
        foreach ($this->scope->nodes($name) as $node) {
            if (!$this->root->contains($node)) {
                $this->scope->replaceNode($name, $node, []);
            }
        }
    }

    /**
     * Build the tree once: resolve template inserts, then clean up. Locks the template.
     */
    protected function build(BuildContext $ctx): void
    {
        if ($this->built) {
            return;
        }
        // Hidden content first, so templates inside it are never built.
        // render() has already ruled out circular inserts (checkInserts()).
        $this->removeHidden();
        $collected = $this->resolvePending($ctx);
        $this->assets->build($this->doc, $this->root, $this->endNode(), $collected);
        $this->stripMarkers(true);
        $this->built = true;
    }

    /**
     * Walk the inserts that will be built and reject circular or already-rendered ones,
     * before anything is built, so a failed render leaves every template unchanged.
     * Inserts inside hidden content are skipped: they are never built.
     *
     * @param list<Template> $stack
     * @param \WeakMap<Template, true> $checked
     * @throws \LogicException
     */
    private function checkInserts(array $stack, \WeakMap $checked): void
    {
        if ($stack) {
            $this->assertInsertable();
        }
        if ($this->built || isset($checked[$this])) {
            return;
        }
        if (in_array($this, $stack, true)) {
            $labels = array_map(fn(Template $t) => $t->label, [...$stack, $this]);
            throw new \LogicException('Circular template insert: ' . implode(' → ', $labels));
        }
        $stack[] = $this;
        /** @var \WeakMap<\Dom\Node, true> $removed */
        $removed = new \WeakMap();
        foreach ($this->removedNodes() as $node) {
            $removed[$node] = true;
        }
        foreach ($this->pending as [$slot, $child]) {
            // One walk up from the slot: is it in the tree, and outside removed content?
            for ($n = $slot; $n !== null && $n !== $this->root; $n = $n->parentNode) {
                if (isset($removed[$n])) {
                    continue 2;
                }
            }
            if ($n === null) {
                continue;
            }
            $child->checkInserts($stack, $checked);
        }
        $checked[$this] = true;
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
            $this->inherit($child);
            $child->build($ctx);
            array_push($collected, ...$child->assets->bubbled());
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
     * Where inline content goes: the end of a fragment, or of a full page's <body>
     */
    private function endNode(): \Dom\Element|null
    {
        return $this->root instanceof \Dom\Document ? ($this->doc->body ?? $this->doc->documentElement) : $this->root;
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
        foreach ($this->removedNodes() as $node) {
            if ($node->parentNode !== null) {
                $node->remove();
            }
        }
    }

    /**
     * The nodes removeHidden() removes
     *
     * @return list<\Dom\Element>
     */
    private function removedNodes(): array
    {
        $nodes = [];
        foreach ($this->scope->names() as $name) {
            if ($this->scope->kind($name) === Kind::Repeat || !$this->scope->isVisible($name)) {
                array_push($nodes, ...$this->scope->nodes($name));
            }
        }
        return [...$nodes, ...$this->scope->rejected()];
    }

    /**
     * Strip var attributes and, at the top level, any placeholder that was never resolved.
     * <tk-slot> elements written in the HTML are kept.
     */
    private function stripMarkers(bool $dropSlots): void
    {
        if ($dropSlots && self::$slots !== null) {
            foreach ($this->root->querySelectorAll(self::SLOT) as $slot) {
                if (isset(self::$slots[$slot])) {
                    $slot->remove();
                }
            }
        }
        foreach ($this->root->querySelectorAll('[var]') as $el) {
            $el->removeAttribute('var');
        }
    }
}
