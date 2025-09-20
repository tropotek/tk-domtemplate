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
     *
     * All the code to modify your Template variable elements should reside here
     * and once done, return the Template.
     *
     * Not to be called after the template has been parsed, use `$template->isParsed()` if you need to check.
     */
    function show(): ?Template;

}