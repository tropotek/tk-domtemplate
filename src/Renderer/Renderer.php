<?php
namespace Tk\DomTemplate\Renderer;

use Tk\DomTemplate\Template;

/**
 * Base class for renderers. Override makeTemplate() to create the template on first use,
 * and show() to fill it in. Cloning a renderer clones its template.
 */
abstract class Renderer implements RendererInterface
{
    use RendererTrait;

    public function __clone()
    {
        if ($this->template !== null) {
            $this->template = clone $this->template;
        }
    }
}
