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
 * @see http://www.domtemplate.com/
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
abstract class Element
{

    protected ?\DOMElement $element = null;

    protected ?Form $form = null;


    public function __construct(\DOMElement $element, Form $form = null)
    {
        $this->element = $element;
        $this->form = $form;
    }

    /**
     * Set the name of this element
     */
    public function setName(string $name): Element
    {
        $this->element->setAttribute('name', $name);
        return $this;
    }

    /**
     * Get the name of this element
     */
    public function getName(): string
    {
        return $this->element->getAttribute('name');
    }

    /**
     * Get the DomElement node for this form element
     */
    public function getNode(): ?\DOMElement
    {
        return $this->element;
    }

    /**
     * Get the parent DOM form object
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * Get the Type's Template
     */
    public function getTemplate(): ?Template
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
     * @param string|array $value
     */
    abstract function setValue($value): Element;

    /**
     * Return the value of the element, or the selected value.
     *
     * @return string|array A string or an array of strings for multiple select elements
     */
    abstract function getValue();

    /**
     * Return the form element type attribute
     */
    public function getType(): string
    {
        return $this->element->getAttribute('type');
    }


    /**
     * Disable this element, adds a disable attribute to the node
     */
    public function disable(): Element
    {
        $this->element->setAttribute('disabled', 'disabled');
        return $this;
    }

    /**
     * get the disabled state of this node
     */
    public function isDisabled(): bool
    {
        return $this->element->hasAttribute('disabled');

    }

    /**
     * Set the attribute name and value
     */
    public function setAttribute(string $name, string $value): Element
    {
        $this->element->setAttribute($name, $value);
        return $this;
    }

    /**
     * Set the name of this element
     */
    public function getAttribute(string $name): string
    {
        return $this->element->getAttribute($name);
    }
}