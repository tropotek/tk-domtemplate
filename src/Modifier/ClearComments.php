<?php
namespace Tk\DomTemplate\Modifier;

/**
 * Remove every HTML comment from the output
 */
final class ClearComments extends BaseModifier
{
    /**
     * Comments are all this modifier needs, so it asks for no elements
     */
    public function selector(): string
    {
        return ':not(*)';
    }

    public function comment(\Dom\Comment $comment, Context $ctx): void
    {
        $comment->remove();
    }
}
