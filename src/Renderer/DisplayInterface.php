<?php
namespace Tk\DomTemplate\Renderer;

use Tk\DomTemplate\Template;

/**
 * Something that produces a template to display
 */
interface DisplayInterface
{
    public function show(): ?Template;
}
