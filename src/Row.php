<?php
namespace Tk\DomTemplate;

/**
 * One row of a repeat: a clone of the sample node with the full template API.
 *
 * Fill it in, then add it once with append(), prepend(), appendTo() or prependTo().
 * After that it is locked. Create rows with Template::repeat() or Template::each().
 */
final class Row extends Template
{
    private bool $added = false;

    /**
     * @internal use Template::repeat()
     */
    public function __construct(
        private readonly Template $parentTemplate,
        private readonly \Dom\Element $sample,
        private readonly string $name,
    ) {
        parent::__construct($parentTemplate->doc, Html::cloneDeep($sample), $parentTemplate->label(), null);
    }

    public function __clone()
    {
        throw new \LogicException('Rows cannot be cloned; create another with Template::repeat()');
    }

    public static function load(string $html, ?Engine $engine = null): static
    {
        throw new \LogicException('Rows are created with Template::repeat()');
    }

    public static function file(string $path, ?Engine $engine = null): static
    {
        throw new \LogicException('Rows are created with Template::repeat()');
    }

    public static function fromNode(\Dom\Element $node, ?Engine $engine = null, string $label = '(node)'): static
    {
        throw new \LogicException('Rows are created with Template::repeat()');
    }

    public function render(): string
    {
        throw new \LogicException(sprintf('Row `%s` renders with its template; add it with append()', $this->name));
    }

    /**
     * Add the row where the sample is, after any rows already added from it
     */
    public function append(string $name = '', string|Template $html = ''): static
    {
        if (func_num_args() === 1) {
            throw new \ArgumentCountError('Row::append() takes no arguments to add the row, or a var name and HTML');
        }
        if (func_num_args() === 2) {
            parent::append($name, $html);
            return $this;
        }
        $this->add(false);
        return $this;
    }

    /**
     * Add the row before all rows already added from the same sample
     */
    public function prepend(string $name = '', string|Template $html = ''): static
    {
        if (func_num_args() === 1) {
            throw new \ArgumentCountError('Row::prepend() takes no arguments to add the row, or a var name and HTML');
        }
        if (func_num_args() === 2) {
            parent::prepend($name, $html);
            return $this;
        }
        $this->add(true);
        return $this;
    }

    /**
     * Add the row as the last child of every node with this var name.
     * The name is looked up in the template the row belongs to, then outward.
     */
    public function appendTo(string $name): void
    {
        $this->addTo($name, false);
    }

    /**
     * Add the row as the first child of every node with this var name
     */
    public function prependTo(string $name): void
    {
        $this->addTo($name, true);
    }

    /**
     * Rows use their template's engine, whatever it is when they're used
     */
    public function engine(): Engine
    {
        return $this->parentTemplate->engine();
    }

    /**
     * The sample was checked when its template loaded, so mistakes aren't reported again per row
     */
    protected function reportMarkup(string $message): void
    {
    }

    public function isAdded(): bool
    {
        return $this->added;
    }

    protected function isFinished(): bool
    {
        return $this->added || $this->parentTemplate->isFinished();
    }

    public function assertMutable(): void
    {
        if ($this->added) {
            throw new \LogicException(sprintf('Row `%s` in %s has already been added', $this->name, $this->label()));
        }
        $this->parentTemplate->assertMutable();
    }

    protected function parent(): Template
    {
        return $this->parentTemplate;
    }

    /**
     * Finish the row's own subtree and return its element, or null when it shouldn't be inserted
     */
    private function finish(): ?\Dom\Element
    {
        $this->assertMutable();
        $this->cleanup(false);
        $this->added = true;
        $node = $this->root;
        assert($node instanceof \Dom\Element);
        $node->removeAttribute('var');
        $rootIsSelf = $this->scope->nodes($this->name) === [$node];
        if (($rootIsSelf && !$this->scope->isVisible($this->name)) || $this->sample->parentNode === null) {
            return null;
        }
        return $node;
    }

    private function addTo(string $name, bool $prepend): void
    {
        $this->assertMutable();
        $targets = null;
        $owner = $this->parentTemplate;
        for ($t = $this->parentTemplate; $t !== null; $t = $t->parent()) {
            $kind = $t->scope->kind($name);
            if ($kind !== null && $kind !== Kind::Repeat) {
                $targets = $t->scope->nodes($name);
                $owner = $t;
                break;
            }
        }
        if ($targets === null) {
            $this->engine()->fail(sprintf('No var `%s` in %s for appendTo()', $name, $this->label()));
            $targets = [];
        }
        $node = $this->finish();
        $inserted = [];
        if ($node !== null) {
            foreach ($targets as $i => $target) {
                $insert = $i === 0 ? $node : $this->cloneWithInserts($node);
                $prepend ? $target->prepend($insert) : $target->append($insert);
                $inserted[] = $insert;
            }
        }
        // The template holding the targets takes the inserts and assets, so they aren't
        // lost when the row's own parent row is never added
        $owner->adopt($this, $inserted);
    }

    /**
     * Clone a finished row for another target. Its template placeholders get their own
     * pending inserts, so the same child templates are rendered into the copy too.
     */
    private function cloneWithInserts(\Dom\Element $node): \Dom\Element
    {
        $clone = Html::cloneDeep($node);
        $children = new \WeakMap();
        foreach ($this->pending as [$slot, $child]) {
            $children[$slot] = $child;
        }
        $copies = iterator_to_array($clone->querySelectorAll(self::SLOT), false);
        foreach (iterator_to_array($node->querySelectorAll(self::SLOT), false) as $i => $slot) {
            if (isset($children[$slot], $copies[$i])) {
                $this->pending[] = [$copies[$i], $children[$slot], null];
            }
        }
        // Nested rows' assets follow into the copy
        /** @var \WeakMap<\Dom\Element, \Dom\Element> $map */
        $map = new \WeakMap();
        $old = [$node, ...iterator_to_array($node->getElementsByTagName('*'), false)];
        $new = [$clone, ...iterator_to_array($clone->getElementsByTagName('*'), false)];
        foreach ($old as $i => $el) {
            $map[$el] = $new[$i];
        }
        self::copySlotMarks($map);
        foreach ($this->inlineAt as [$anchor, $asset]) {
            if (isset($map[$anchor])) {
                $this->inlineAt[] = [$map[$anchor], $asset];
            }
        }
        foreach ($this->rowAssets as $i => [$nodes, $asset]) {
            foreach ($nodes as $n) {
                if (isset($map[$n])) {
                    $this->rowAssets[$i][0][] = $map[$n];
                }
            }
        }
        return $clone;
    }

    private function add(bool $prepend): void
    {
        $node = $this->finish();
        if ($node !== null) {
            $this->parentTemplate->placeRow($this->sample, $node, $prepend);
        }
        $this->parentTemplate->adopt($this, $node ? [$node] : []);
    }
}
