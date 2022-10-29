<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * @author Template <http://www.tropotek.com/>
 */
interface RendererInterface extends DisplayInterface
{
    public function getTemplate(): ?Template;

    public function setTemplate(Template $template);
}