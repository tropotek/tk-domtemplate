<?php
namespace Dom\Renderer;

/**
 * Template Bootstrap interface
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
interface Iface
{

    /**
     * Execute the renderer.
     * Return an object that your framework can interpret and display.
     *
     * @return mixed
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