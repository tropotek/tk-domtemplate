<?php
namespace Tk\DomTemplate\Renderer;

use Tk\DomTemplate\Template;

/**
 * A renderer that owns the template it displays
 */
interface RendererInterface extends DisplayInterface
{
    public function getTemplate(): ?Template;

    public function setTemplate(Template $template): void;
}
