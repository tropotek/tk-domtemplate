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
     * @internal use Template::var()
     */
    public function __construct(
        private readonly Template $owner,
        private readonly string $name,
        private array $nodes,
        private readonly HandleOps $ops,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the text content, escaped. In <script> and <style> the sequences that could end
     * the element are escaped instead; other raw-text elements (<iframe>, <xmp> …) are refused.
     */
    public function text(string $text): static
    {
        ($this->ops->assertMutable)();
        $text = Html::utf8($text);
        foreach ($this->contentNodes() as $node) {
            $tag = strtolower($node->localName);
            if (in_array($tag, ['iframe', 'xmp', 'noembed', 'noframes', 'plaintext'], true)) {
                $this->owner->engine()->fail(sprintf("text() can't escape content for <%s> `%s` in %s", $tag, $this->name, $this->owner->label()));
                continue;
            }
            if ($tag === 'script' && !self::isScriptData($node)) {
                // A template or other non-JS type: its text is kept as written, so it
                // can't hold the sequences that would end the element early
                if (Html::breaksScript($text)) {
                    $this->owner->engine()->fail(sprintf("text() can't safely write `<!--` or `</script` into <script type=\"%s\"> `%s` in %s",
                        $node->getAttribute('type'), $this->name, $this->owner->label()));
                    continue;
                }
                $node->textContent = $text;
                continue;
            }
            $node->textContent = match ($tag) {
                'script' => Html::scriptText($text),
                'style' => Html::styleText($text),
                default => $text,
            };
        }
        ($this->ops->pruneDetached)($this->name);
        return $this;
    }

    /**
     * Replace the contents with HTML (parsed in the context of each node) or a template.
     * A template is kept as a reference and rendered when the page is built.
     */
    public function html(string|Template $html): static
    {
        ($this->ops->assertMutable)();
        foreach ($this->contentNodes($html) as $node) {
            if ($html instanceof Template) {
                $node->replaceChildren(($this->ops->slot)($html));
            } else {
                $node->innerHTML = $html;
            }
        }
        ($this->ops->pruneDetached)($this->name);
        return $this;
    }

    /**
     * Add HTML or a template at the end of each node
     */
    public function append(string|Template $html): static
    {
        return $this->adjacent(\Dom\AdjacentPosition::BeforeEnd, $html);
    }

    /**
     * Add HTML or a template at the start of each node
     */
    public function prepend(string|Template $html): static
    {
        return $this->adjacent(\Dom\AdjacentPosition::AfterBegin, $html);
    }

    /**
     * Replace each node itself with HTML. The name (and this handle) then refer to the
     * replacement elements, so show()/hide() and later changes apply to them.
     */
    public function replace(string|Template $html): static
    {
        ($this->ops->assertMutable)();
        $replaced = [];
        $gone = [];
        foreach ($this->nodes as $node) {
            foreach ($gone as $old) {
                if ($old->contains($node)) {
                    // Nested inside a node of the same name replaced earlier in this loop
                    ($this->ops->replaceNode)($this->name, $node, []);
                    continue 2;
                }
            }
            $gone[] = $node;
            $parent = $node->parentNode;
            if ($parent === null) {
                throw new \LogicException(sprintf("Can't replace `%s`: a row can't replace its own element", $this->name));
            }
            if ($html instanceof Template) {
                $slot = ($this->ops->slot)($html, $this->name);
                $node->replaceWith($slot);
                ($this->ops->replaceNode)($this->name, $node, [$slot]);
                $replaced[] = $slot;
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
            ($this->ops->replaceNode)($this->name, $node, $new);
            array_push($replaced, ...$new);
        }
        $this->nodes = $replaced;
        return $this;
    }

    /**
     * Set one attribute, or several from an array.
     * true, or no value, sets a bare attribute (disabled=""); false and null remove it.
     *
     * @param string|array<string, string|int|float|bool|null> $name
     */
    public function attr(string|array $name, string|int|float|bool|null $value = null): static
    {
        ($this->ops->assertMutable)();
        $attrs = is_array($name) ? $name : [$name => func_num_args() === 1 ? true : $value];
        foreach (array_keys($attrs) as $attr) {
            if (!is_string($attr) || !Html::isAttrName($attr)) {
                throw new TemplateException(sprintf('Invalid attribute name `%s` for var `%s`', $attr, $this->name));
            }
        }
        foreach ($this->nodes as $node) {
            foreach ($attrs as $attr => $val) {
                if ($val === null || $val === false) {
                    $node->removeAttribute((string)$attr);
                } else {
                    Html::setAttribute($node, (string)$attr, $val === true ? '' : Html::utf8((string)$val));
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
        ($this->ops->assertMutable)();
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
        ($this->ops->assertMutable)();
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
        ($this->ops->assertMutable)();
        if ($this->nodes) {
            ($this->ops->setVisible)($this->name, $visible);
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
        if (in_array(strtolower($name), [Html::TRUST_ATTR, Asset::KEY_ATTR], true)) {
            return null;
        }
        return isset($this->nodes[0]) ? $this->nodes[0]->getAttribute($name) : null;
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * The nodes whose content can be changed. A placeholder left by replace($template)
     * is skipped (its content would be lost), and so is a <template> element when inserting
     * a template (its content isn't part of the tree); both are reported.
     *
     * @return list<\Dom\Element>
     */
    private function contentNodes(string|Template $html = ''): array
    {
        $nodes = [];
        foreach ($this->nodes as $node) {
            if (($this->ops->isSlot)($node)) {
                $this->owner->engine()->fail(sprintf('`%s` in %s was replaced by a template; change that template instead', $this->name, $this->owner->label()));
            } elseif ($html instanceof Template && $node->localName === 'template') {
                $this->owner->engine()->fail(sprintf("Can't insert a template into <template> `%s` in %s; insert its render() string instead", $this->name, $this->owner->label()));
            } else {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    private function adjacent(\Dom\AdjacentPosition $where, string|Template $html): static
    {
        ($this->ops->assertMutable)();
        foreach ($this->contentNodes($html) as $node) {
            if ($html instanceof Template) {
                $slot = ($this->ops->slot)($html);
                $where === \Dom\AdjacentPosition::BeforeEnd ? $node->append($slot) : $node->prepend($slot);
            } elseif ($node->localName === 'template') {
                // A <template>'s content isn't part of the tree, so it is re-set as a whole
                $node->innerHTML = $where === \Dom\AdjacentPosition::BeforeEnd ? $node->innerHTML . $html : $html . $node->innerHTML;
            } else {
                $node->insertAdjacentHTML($where, $html);
            }
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
            foreach (preg_split('/\s+/', trim(Html::utf8($part)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * True for a <script> whose content is JavaScript or JSON (no type, a JS or JSON type,
     * module, importmap, speculationrules): its text can be escaped without changing its value
     */
    private static function isScriptData(\Dom\Element $node): bool
    {
        $type = strtolower(trim((string)$node->getAttribute('type')));
        return $type === '' || in_array($type, ['module', 'importmap', 'speculationrules'], true)
            || str_contains($type, 'javascript') || str_contains($type, 'ecmascript') || str_contains($type, 'json');
    }
}
