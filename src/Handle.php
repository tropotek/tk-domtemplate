<?php
namespace Tk\DomTemplate;

/**
 * Chainable operations on every node with one var name.
 *
 * A handle with no nodes (an unknown name in lenient mode) does nothing.
 */
final class Handle
{
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
     * Replace each node itself with HTML. The handle then holds no nodes.
     */
    public function replaceWith(string $html): static
    {
        $this->owner->assertMutable();
        foreach ($this->nodes as $node) {
            $node->outerHTML = $html;
        }
        $this->nodes = [];
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
        foreach ($this->nodes as $node) {
            foreach ($attrs as $attr => $val) {
                if ($val === null || $val === false) {
                    $node->removeAttribute($attr);
                } else {
                    $node->setAttribute($attr, $val === true ? '' : (string)$val);
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
