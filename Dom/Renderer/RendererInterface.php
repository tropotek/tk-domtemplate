<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * Template Renderer interface
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
interface RendererInterface
{
    /**
     * Get the Template
     *
     * @return Template
     */
    public function getTemplate();

    /**
     * Set the Template
     *
     * @param Template $template
     */
    public function setTemplate($template);

}