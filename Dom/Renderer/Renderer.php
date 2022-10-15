<?php
namespace Dom\Renderer;

use Dom\Renderer\Traits\RendererTrait;

/**
 * For classes that render \Dom\Templates.
 *
 * This is a good base for all renderer objects that implement the \Dom\Template
 * it can guide you to create templates that can be inserted into other templates.
 *
 * If the current template is null then
 * the magic method __makeTemplate() will be called to create an internal template.
 * This is a good way to create a default template. But be aware that this will
 * be a new template and will have to be inserted into its parent using the \Dom_Template::insertTemplate()
 * method.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class Renderer implements RendererInterface, DisplayInterface
{
    use RendererTrait;

    public function __clone()
    {
        $this->template = clone $this->template;
    }

}