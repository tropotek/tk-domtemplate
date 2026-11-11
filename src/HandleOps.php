<?php
namespace Tk\DomTemplate;

/**
 * The Template helpers a Handle needs, handed over as callables so they stay off
 * Template's public API. Made once per template by Template::var().
 *
 * @internal
 */
final readonly class HandleOps
{
    /**
     * @param \Closure(): void $assertMutable
     * @param \Closure(Template, ?string=): \Dom\Element $slot
     * @param \Closure(string, \Dom\Element, list<\Dom\Element>): void $replaceNode
     * @param \Closure(string, bool): void $setVisible
     * @param \Closure(string): void $pruneDetached
     * @param \Closure(\Dom\Element): bool $isSlot
     */
    public function __construct(
        public \Closure $assertMutable,
        public \Closure $slot,
        public \Closure $replaceNode,
        public \Closure $setVisible,
        public \Closure $pruneDetached,
        public \Closure $isSlot,
    ) {}
}
