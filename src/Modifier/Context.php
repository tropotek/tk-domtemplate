<?php
namespace Tk\DomTemplate\Modifier;

/**
 * What a modifier is processing: the document, the root (the document for a full page,
 * a detached container for a fragment) and whether it is a fragment.
 *
 * A new Context is made for every render, so per-render data belongs in store(),
 * never in properties of the modifier: an Engine (and its modifiers) is shared across
 * requests in worker mode, and a modifier may render other templates mid-pass.
 *
 * Nodes can be removed or replaced directly: element lists are static, so iteration is safe.
 */
final readonly class Context
{
    /** @var \WeakMap<object, \ArrayObject<string, mixed>> */
    private \WeakMap $stores;

    /** @var \ArrayObject<int, \Dom\Element> elements the CSP nonce is added to after the modifiers */
    private \ArrayObject $trusted;

    /**
     * @param ?\ArrayObject<int, \Dom\Element> $trusted
     */
    public function __construct(
        public \Dom\HTMLDocument $document,
        public \Dom\Element|\Dom\Document $root,
        public bool $fragment,
        ?\ArrayObject $trusted = null,
    ) {
        $this->stores = new \WeakMap();
        /** @var \ArrayObject<int, \Dom\Element> $none */
        $none = new \ArrayObject();
        $this->trusted = $trusted ?? $none;
    }

    /**
     * Let the engine's CSP nonce be added to a <script>, <style> or <link> this modifier
     * created. Elements copied from page content should not be trusted. Returns the element.
     */
    public function trust(\Dom\Element $el): \Dom\Element
    {
        $this->trusted[] = $el;
        return $el;
    }

    /**
     * Data that $owner (usually the modifier itself) keeps for this render only
     *
     * @return \ArrayObject<string, mixed>
     */
    public function store(object $owner): \ArrayObject
    {
        return $this->stores[$owner] ??= new \ArrayObject();
    }
}
