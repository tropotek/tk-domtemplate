<?php
namespace Dom\Form;

use Dom\Form;
use Dom\Template;

/**
 * All form elements must use this class/interface.
 *
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
 */
abstract class Element
{

    /**
     * This could be a single \DOMElement or an array of \DOMElement
     * @var \DOMElement
     */
    protected $element = null;

    /**
     * @var Form
     */
    protected $form = null;

    /**
     * __construct
     *
     * @param \DOMElement $element
     * @param Form $form
     */
    public function __construct($element, $form = null)
    {
        $this->element = $element;
        $this->form = $form;
    }

    /**
     * Set the name of this element
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->element->setAttribute('name', $name);
        return $this;
    }

    /**
     * Get the name of this element
     *
     * @return string The name of this element.
     */
    public function getName()
    {
        return $this->element->getAttribute('name');
    }

    /**
     * Get the \DomElement node for this form element
     *
     * @return \DOMElement
     */
    public function getNode()
    {
        return $this->element;
    }

    /**
     * Get the parent DOM form object
     *
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Get the Element's Template
     *
     * @return Template
     */
    public function getTemplate()
    {
        if ($this->form) {
            return $this->form->getTemplate();
        }
        return null;
    }

    /**
     * Set the value of a form element.
     *
     * Set value behaves different for different elements:
     *  o input => This is the element value attribute
     *  o checkbox/radio => The value to check/select
     *  o select => The value of the option to be selected
     *  o textarea => the content of the textarea
     *
     * @param string $value
     * @return Element
     */
    abstract function setValue($value);

    /**
     * Return the value of the element, or the selected value.
     *
     * @return string|array A string or an array of strings for multiple select elements
     */
    abstract function getValue();

    /**
     * Return the form element type attribute
     *
     * @return string
     */
    public function getType()
    {
        return $this->element->getAttribute('type');
    }

    /**
     * Disable this element, adds a disable attribute to the node
     *
     * @return $this
     */
    public function disable()
    {
        $this->element->setAttribute('disabled', 'disabled');
        return $this;
    }

    /**
     * get the disabled state of this node
     *
     * @return bool
     */
    public function isDisabled()
    {
        return $this->element->hasAttribute('disabled');

    }

    /**
     * Set the attribute name and value
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setAttribute($name, $value)
    {
        $this->element->setAttribute($name, $value);
        return $this;
    }

    /**
     * Set the name of this element
     *
     * @param string $name
     * @return string
     */
    public function getAttribute($name)
    {
        return $this->element->getAttribute($name);
    }
}