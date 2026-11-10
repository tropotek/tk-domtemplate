<?php
namespace Tk\DomTemplate;

/**
 * Chainable operations on every node with one var name.
 *
 * A handle with no nodes (an unknown name in lenient mode) does nothing.
 */
final class Handle
{
    /** Characters HTML allows in an attribute name (Alpine's @click and :class included) */
    private const string ATTR_NAME = '/^[^\s"\'>\/=\x00-\x1F\x7F]+$/';

    /**
     * @param list<\Dom\Element> $nodes
     */
    public function __construct(
        private readonly Template $owner,
        private readonly string $name,
        private array $nodes,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the text content, escaped
     */
    public function text(string $text): static
    {
        $this->owner->assertMutable();
        foreach ($this->nodes as $node) {
            $node->textContent = $text;
        }
        return $this;
    }

    /**
     * Replace the contents with HTML, parsed in the context of each node
     */
    public function html(string $html): static
    {
        $this->owner->assertMutable();
        foreach ($this->nodes as $node) {
            $node->innerHTML = $html;
        }
        return $this;
    }

    public function append(string $html): static
    {
        return $this->adjacent(\Dom\AdjacentPosition::BeforeEnd, $html);
    }

    public function prepend(string $html): static
    {
        return $this->adjacent(\Dom\AdjacentPosition::AfterBegin, $html);
    }

    /**
     * Replace each node itself with HTML. The name (and this handle) then refer to the
     * replacement elements, so show()/hide() and later changes apply to them.
     */
    public function replaceWith(string $html): static
    {
        $this->owner->assertMutable();
        $replaced = [];
        foreach ($this->nodes as $node) {
            $parent = $node->parentNode;
            if ($parent === null) {
                continue;
            }
            $before = $node->previousSibling;
            $after = $node->nextSibling;
            $node->outerHTML = $html;
            $new = [];
            for ($n = $before ? $before->nextSibling : $parent->firstChild; $n !== null && $n !== $after; $n = $n->nextSibling) {
                if ($n instanceof \Dom\Element) {
                    $new[] = $n;
                }
            }
            $this->owner->replaceNode($this->name, $node, $new);
            array_push($replaced, ...$new);
        }
        $this->nodes = $replaced;
        return $this;
    }

    /**
     * Set one attribute, or several from an array.
     * true sets an empty attribute; false and null remove it.
     *
     * @param string|array<string, string|int|float|bool|null> $name
     */
    public function attr(string|array $name, string|int|float|bool|null $value = null): static
    {
        $this->owner->assertMutable();
        $attrs = is_array($name) ? $name : [$name => $value];
        foreach (array_keys($attrs) as $attr) {
            if (!is_string($attr) || !preg_match(self::ATTR_NAME, $attr)) {
                throw new TemplateException(sprintf('Invalid attribute name `%s` for var `%s`', $attr, $this->name));
            }
        }
        foreach ($this->nodes as $node) {
            foreach ($attrs as $attr => $val) {
                if ($val === null || $val === false) {
                    $node->removeAttribute((string)$attr);
                } else {
                    self::setAttribute($node, (string)$attr, $val === true ? '' : (string)$val);
                }
            }
        }
        return $this;
    }

    public function removeAttr(string $name): static
    {
        return $this->attr($name, null);
    }

    /**
     * @param string|list<string> $class one or more class names; a string may hold several separated by spaces
     */
    public function addClass(string|array $class): static
    {
        $this->owner->assertMutable();
        $tokens = self::tokens($class);
        foreach ($this->nodes as $node) {
            if ($tokens) {
                $node->classList->add(...$tokens);
            }
        }
        return $this;
    }

    /**
     * Remove whole class tokens: removing "btn" leaves "btn-primary" alone
     *
     * @param string|list<string> $class
     */
    public function removeClass(string|array $class): static
    {
        $this->owner->assertMutable();
        $tokens = self::tokens($class);
        foreach ($this->nodes as $node) {
            if ($tokens) {
                $node->classList->remove(...$tokens);
            }
            if ($node->getAttribute('class') === '') {
                $node->removeAttribute('class');
            }
        }
        return $this;
    }

    /**
     * Keep the nodes in the output (true) or remove them at render (false)
     */
    public function show(bool $visible = true): static
    {
        $this->owner->assertMutable();
        if ($this->nodes) {
            $this->owner->setVisible($this->name, $visible);
        }
        return $this;
    }

    /**
     * Remove the nodes at render
     */
    public function hide(): static
    {
        return $this->show(false);
    }

    /**
     * The first node's text, or '' when there are no nodes
     */
    public function getText(): string
    {
        return isset($this->nodes[0]) ? (string)$this->nodes[0]->textContent : '';
    }

    public function getAttr(string $name): ?string
    {
        return isset($this->nodes[0]) ? $this->nodes[0]->getAttribute($name) : null;
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    private function adjacent(\Dom\AdjacentPosition $where, string $html): static
    {
        $this->owner->assertMutable();
        foreach ($this->nodes as $node) {
            $node->insertAdjacentHTML($where, $html);
        }
        return $this;
    }

    /**
     * setAttribute() rejects names the HTML parser accepts, such as Alpine's @click.
     * Those are created by parsing them, then attached as attribute nodes.
     */
    private static function setAttribute(\Dom\Element $node, string $name, string $value): void
    {
        try {
            $node->setAttribute($name, $value);
        } catch (\DOMException) {
            $scratch = ($node->ownerDocument ?? throw new TemplateException('Node has no document'))->createElement('div');
            $scratch->innerHTML = '<i ' . $name . '=""></i>';
            $holder = $scratch->firstElementChild;
            $attr = $holder?->getAttributeNode($name);
            if ($holder === null || $attr === null) {
                throw new TemplateException(sprintf('Invalid attribute name `%s`', $name));
            }
            $holder->removeAttributeNode($attr);
            $attr->value = $value;
            $node->setAttributeNode($attr);
        }
    }

    /**
     * @param string|list<string> $class
     * @return list<string>
     */
    private static function tokens(string|array $class): array
    {
        $parts = is_array($class) ? $class : [$class];
        $tokens = [];
        foreach ($parts as $part) {
            foreach (preg_split('/\s+/', trim($part), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
}
