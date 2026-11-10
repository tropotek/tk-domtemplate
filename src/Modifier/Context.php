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

    public function __construct(
        public \Dom\HTMLDocument $document,
        public \Dom\Element|\Dom\Document $root,
        public bool $fragment,
    ) {
        $this->stores = new \WeakMap();
    }

    /**
     * Let the engine's CSP nonce be added to a <script>, <style> or <link> this modifier
     * created. Elements copied from page content should not be trusted. Returns the element.
     */
    public function trust(\Dom\Element $el): \Dom\Element
    {
        return \Tk\DomTemplate\Html::trust($el);
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
