<?php
namespace Dom\Renderer\Traits;

use Dom\Template;

/**
 * Class RendererTrait
 *
 * In rare cases use this to add the get/set template to your renderer object
 * Do not forget to implement the DisplayInterface if you need the show() method
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
trait RendererTrait
{

    protected ?Template $template = null;


    /**
     * Set a new template for this renderer.
     */
    public function setTemplate(Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get the template
     * This method will try to call the magic method __makeTemplate
     * to create a template if non exits.
     */
    public function getTemplate(): ?Template
    {
        $magic = '__makeTemplate';
        if (!$this->hasTemplate() && method_exists($this, $magic)) {
            $this->template = $this->$magic();
        }
        return $this->template;
    }

    /**
     * Test if this renderer has a template and is not NULL
     */
    public function hasTemplate(): bool
    {
        return ($this->template != null);
    }
}