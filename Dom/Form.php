<?php
namespace Dom;

use Dom\Form\Element;
use Dom\Form\Input;
use Dom\Form\Select;
use Dom\Form\Textarea;

/**
 * The form package make an API available for rendering a form and its elements
 *
 * The form package currently does not fully support element multidimensional arrays.
 * It can be done it is not fully supported or tested.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Form
{

    protected ?\DOMElement $form;

    /**
     * @var array|\DOMElement[]
     */
    protected array $elements;

    protected Template $parent;


    /**
     * @param array|\DOMElement[] $elements
     */
    public function __construct(\DOMElement $form, array $elements, Template $parent)
    {
        $this->form = $form;
        $this->elements = $elements;
        $this->parent = $parent;

    }

    /**
     * Has the form been submitted
     */
    public function isSubmitted(): bool
    {
        if (isset($_REQUEST['domform-'.$this->getId()])) {
            return true;
        }
        return false;
    }

    /**
     * Set/unset the checkboxes and radio boxes.
     * <b>NOTE:</b> This is called by Input<br\>
     *   $value is not required for checkboxes
     */
    public function setCheckedByValue(string $name, string $value = ''): Form
    {
        if (!isset($this->elements[$name])) {
            return $this;
        }
        $elements = $this->elements[$name];
        foreach ($elements as $element) {
            if ($value !== null && ($element->getAttribute('value') == $value)) {
                $element->setAttribute('checked', 'checked');
            } else if ($element->getAttribute('type') == 'radio') {
                $element->removeAttribute('checked');
            }
        }
        return $this;
    }

    /**
     * Return the form element with the name.
     */
    public function getFormElement(string $name, int $i = 0): ?Element
    {
        if (!$this->formElementExists($name)) return null;
        $element = $this->elements[$name][$i];
        $type = $element->nodeName;
        if ($type == 'input' || $type == 'button') {
            return new Input($element, $this);
        } elseif ($type == 'textarea') {
            return new Textarea($element, $this);
        } elseif ($type == 'select') {
            return new Select($element, $this);
        }
        return null;
    }

    /**
     * Get an array of form elements with the name value
     * Used for radio boxes and multi select lists
     *
     * @param string $name
     * @return array
     */
    public function getFormElementList(string $name): array
    {
        if (!$this->formElementExists($name)) return [];
        $nodeList = [];
        $n = count($this->elements[$name]);
        for($i = 0; $i < $n; $i++) {
            $element = $this->elements[$name][$i];
            $type = $element->nodeName;
            if ($type == 'input' || $type == 'button') {
                $nodeList[] = new Input($element, $this);
            } else {
                if ($type == 'textarea') {
                    $nodeList[] = new Textarea($element, $this);
                } else {
                    if ($type == 'select') {
                        $nodeList[] = new Select($element, $this);
                    }
                }
            }
        }
        return $nodeList;
    }

    /**
     * Return the number of elements in an element namespace
     */
    public function getNumFormElements(string $name): int
    {
        return count($this->elements[$name]);
    }

    /**
     * Check if a repeat,choice,var,form (template property) exists.
     */
    public function formElementExists(string $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    /**
     * Get an array containing the form element names
     */
    public function getElementNames(): array
    {
        return array_keys($this->elements);
    }

    /**
     * Set a URL that defines where to send the data when
     *  the submit button is pushed.
     */
    public function setAction(string $value): Form
    {
        if ($this->form) {
            $this->form->setAttribute('action', $value);
        }
        return $this;
    }

    /**
     * The HTTP method for sending data to the action URL.
     * Default is get.<br/>
     * Possible values are:<br/>
     * <ul>
     *   <li>'get'</li>
     *   <li>'post'</li>
     * </ul>
     */
    public function setMethod(string $value): Form
    {
        if ($this->form) {
            $this->form->setAttribute('method', $value);
        }
        return $this;
    }

    /**
     * Set the method used by the form.
     * Possible values are:<br/>
     * <ul>
     *   <li>'_blank'</li>
     *   <li>'_self' (default)</li>
     *   <li>'_parent'</li>
     *   <li>'_top'</li>
     * </ul>
     */
    public function setTarget(string $value): Form
    {
        if ($this->form != null) {
            $this->form->setAttribute('target', $value);
        }
        return $this;
    }

    /**
     * Append a hidden element to a form.
     */
    public function appendHiddenElement(string $name, string $value): \DOMElement
    {
        if ($this->form) {
            $nl = $this->form->ownerDocument->createTextNode("\n");
            $node = $this->form->ownerDocument->createElement('input');
            $node->setAttribute('type', 'hidden');
            $node->setAttribute('name', $name);
            $node->setAttribute('value', $value);
            $this->form->appendChild($node);
            $this->form->appendChild($nl);
            $this->elements[$name][] = $node;
        }
        return $node;
    }

    /**
     * Get an array of the hidden elements in this form
     *
     * @return array|Input[]
     */
    public function getHiddenElements(): array
    {
        $arr = [];
        /* @var \DOMElement $element */
        foreach ($this->elements as $element) {
            $type = $element->nodeName;
            $inputType = $element->getAttribute('type');
            if ($type == 'input' && $inputType == 'hidden') {
                $arr[] = new Input($element, $this);
            }
        }
        return $arr;
    }

    /**
     * Get the form Name Attribute.
     */
    public function getName(): string
    {
        if ($this->form) {
            return $this->form->getAttribute('name');
        }
        return '';
    }

    /**
     * Get the form id attribute
     */
    public function getId(): string
    {
        if ($this->form) {
            return $this->form->getAttribute('id');
        }
        return '';
    }

    /**
     * Get the DOMElement of this form object.
     */
    public function getNode(): ?\DOMElement
    {
        return $this->form;
    }

    /**
     * Get the parent template for this form
     */
    public function getTemplate(): Template
    {
        return $this->parent;
    }
}

