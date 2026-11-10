<?php
namespace Tk\DomTemplate;

use Tk\DomTemplate\Modifier\Context;

/**
 * A final pass over a rendered page or fragment, run by Modifier.
 * Extend Modifier\BaseModifier to implement only what you need.
 */
interface ModifierInterface
{
    /**
     * CSS selector for the elements passed to element(); null for every element
     */
    public function selector(): ?string;

    public function element(\Dom\Element $el, Context $ctx): void;

    public function comment(\Dom\Comment $comment, Context $ctx): void;

    /**
     * Called once after all elements and comments
     */
    public function done(Context $ctx): void;
}
