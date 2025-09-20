<?php
namespace Dom\Form;

use Dom\Template;

/**
 * All form elements must use this class/interface.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class Element
{

    protected ?\DOMElement $element = null;

    protected ?Form $form = null;


    public function __construct(\DOMElement $element, ?Form $form = null)
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
        return $this->form?->getTemplate();
    }

    /**
     * Set the value of a form element.
     *
     * Set value behaves different for different elements:
     *  o input => This is the element value attribute
     *  o checkbox/radio => The value to check/select
     *  o select => The value of the option to be selected
     *  o textarea => the content of the textarea
     */
    abstract function setValue(string|array $value): Element;

    /**
     * Return the value of the element, or the selected value.
     */
    abstract function getValue(): string|array;

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
    public function setAttribute(string $name, ?string $value): Element
    {
        if(is_null($value)) {
            $this->element->removeAttribute($name);
            return $this;
        }
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