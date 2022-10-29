<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * use this on objects that use a show() method and return a template.
 *
 * Extend the Renderer class if you want more template functionality.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
interface DisplayInterface
{

    /**
     * Implement this in your object to be rendered.
     * All the code to modify the Template object should reside here
     * and once done return the Template.
     * Be sure to avoid calling this after the template has been parsed
     *
     * You can call $template->isParsed() and if true return the template to be sure.
     */
    function show(): ?Template;

}