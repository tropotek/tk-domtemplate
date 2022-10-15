<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * @author Template <http://www.tropotek.com/>
 */
interface RendererInterface
{
    /**
     * Get the Template
     */
    public function getTemplate(): ?Template;

    /**
     * Set the Template
     *
     * @param Template $template
     */
    public function setTemplate(Template $template);

}