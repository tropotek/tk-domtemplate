<?php
namespace Dom;

/**
 * Template Renderer interface
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
interface RendererInterface
{

    /**
     * Execute the renderer.
     * This method can optionally return a \Dom_Template
     * or HTML/XML string depending on your framework requirements
     *
     * @param \Dom\Template $template
     * @return \Dom\Template | string
     */
    public function show();

    /**
     * Get the \Dom\Template
     *
     * @return \Dom\Template
     */
    public function getTemplate();

    /**
     * Set the \Dom\Template
     *
     * @param \Dom\Template $template
     */
    public function setTemplate($template);

}