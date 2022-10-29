<?php
namespace Dom\Renderer;

use Dom\Renderer\Traits\RendererTrait;
use Dom\Template;

/**
 * For classes that render \Dom\Templates.
 *
 * This is a good base for all renderer objects that implement the \Dom\Template
 * it can guide you to create templates that can be inserted into other templates.
 *
 * If the current template is null then
 * the magic method __makeTemplate() will be called to create an internal template.
 * This is a good way to create a default template.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class Renderer implements RendererInterface
{
    use RendererTrait;

    public function __clone()
    {
        $this->template = clone $this->template;
    }

    /**
     * Get the template
     * This method will try to call the magic method __makeTemplate
     * to create a template within the object if non exits.
     */
    public function getTemplate(): ?Template
    {
        $magic = '__makeTemplate';
        if (!$this->hasTemplate() && method_exists($this, $magic)) {
            $this->template = $this->$magic();
        }
        return $this->template;
    }
}