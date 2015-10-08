<?php
namespace Dom\Renderer;

/**
 * Class RendererTrait
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
trait RendererTrait
{
    /**
     * @var Template
     */
    protected $template = null;





    /**
     * Set a new template for this renderer.
     *
     * @param \Dom\Template $template
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Get the template
     * This method will try to call the magic method __makeTemplate
     * to create a template if non exits.
     *
     * @return \Dom\Template
     */
    public function getTemplate()
    {
        $magic = '__makeTemplate';
        if (!$this->hasTemplate() && method_exists($this, $magic)) {
            $this->template = $this->$magic();
        }
        return $this->template;
    }

    /**
     * Test if this renderer has a template and is not NULL
     *
     * @return bool
     */
    public function hasTemplate()
    {
        if ($this->template) {
            return true;
        }
        return false;
    }
}