<?php
namespace Dom\Renderer;

use Dom\Template;

/**
 * For classes that render dom templates.
 *
 * This is a good base for all renderer objects that implement the \Dom_Template
 * it can guide you to create templates that can be inserted into other template
 * objects.
 *
 * If the current template is null then
 * the magic method __makeTemplate() will be called to create an internal template.
 * This is a good way to create a default template. But be aware that this will
 * be a new template and will have to be inserted into its parent using the \Dom_Template::insertTemplate()
 * method.
 * 
 * Update:
 * Now the show() method has been removed from the renderer
 * do not forget to implement the DisplayInterface if you need the show() method
 *
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
abstract class Renderer implements RendererInterface
{

    /**
     * @var Template
     */
    protected $template = null;


    /**
     * Set a new template for this renderer.
     *
     * @param Template $template
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
     * @return Template
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