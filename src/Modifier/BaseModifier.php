<?php
namespace Tk\DomTemplate\Modifier;

use Tk\DomTemplate\ModifierInterface;

/**
 * A modifier with no-op hooks: override the ones you need
 */
abstract class BaseModifier implements ModifierInterface
{
    public function selector(): ?string
    {
        return null;
    }

    public function element(\Dom\Element $el, Context $ctx): void
    {
    }

    public function comment(\Dom\Comment $comment, Context $ctx): void
    {
    }

    public function done(Context $ctx): void
    {
    }
}
