<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * Template Bootstrap interface
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
interface DisplayInterface extends RendererInterface
{
    /**
     * Execute the renderer.
     * Return an object that your framework can interpret and display.
     *
     * @return null|Template|Renderer
     */
    public function show();

}