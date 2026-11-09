<?php
namespace Tk\DomTemplate\Renderer;

use Tk\DomTemplate\Template;

/**
 * @author Template <http://www.tropotek.com/>
 */
interface RendererInterface extends DisplayInterface
{
    public function getTemplate(): ?Template;

    public function setTemplate(Template $template): void;
}