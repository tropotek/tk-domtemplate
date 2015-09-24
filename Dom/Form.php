<?php
namespace Dom;

use Dom\Form\Element;
use Dom\Form\Input;
use Dom\Form\Select;
use Dom\Form\Textarea;

/**
 * The form package make an API available for rendering a form and its elements
 *
 * The form package currently does not fully support element arrays.
 * It can be done but it is not fully supported or tested.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
 */
class Form
{

    /**
     * @var \DOMElement
     */
    protected $form = null;

    /**
     * An Array of Element objects
     * @var array Element
     */
    protected $elements = array();

    /**
     * @var Template
     */
    protected $parent = null;

    /**
     * __construct
     *
     * @param \DOMElement $form
     * @param array $elements An array of form elements
     * @param Template $parent The parent object
     */
    public function __construct($form, $elements, $parent)
    {
        $this->form = $form;
        $this->parent = $parent;
        $this->elements = $elements;

    }

    /**
     * Has the form been submitted
     *
     * @return bool
     */
    public function isSubmitted()
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
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setCheckedByValue($name, $value = '')
    {
        if (!isset($this->elements[$name])) {
            return $this;
        }
        $elements = $this->elements[$name];
        /** @var \DOMElement $element */
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
     *
     * @param string $name
     * @param int $i (optional) index for multiple elements
     * @return Element
     */
    public function getFormElement($name, $i = 0)
    {
        if (!$this->formElementExists($name)) {
            return null;
        }
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
    public function getFormElementList($name)
    {
        if (!$this->formElementExists($name)) {
            return array();
        }
        $nodeList = array();
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
     *
     * @param string $name
     * @return int
     */
    public function getNumFormElements($name)
    {
        return count($this->elements[$name]);
    }

    /**
     * Check if a repeat,choice,var,form (template property) exists.
     *
     * @param string $key
     * @return bool
     */
    public function formElementExists($key)
    {
        if (!array_key_exists($key, $this->elements)) {
            return false;
        }
        return true;
    }

    /**
     * Get an array containing the form element names
     *
     * @return array
     */
    public function getElementNames()
    {
        return array_keys($this->elements);
    }

    /**
     * Set a URL that defines where to send the data when
     *  the submit button is pushed.
     *
     * @param string $value
     * @return $this
     */
    public function setAction($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('action', Template::objectToString($value));
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
     *
     * @param string $value
     * @return $this
     */
    public function setMethod($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('method', Template::objectToString($value));
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
     *
     * @param string $value
     * @return $this
     */
    public function setTarget($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('target', Template::objectToString($value));
        }
        return $this;
    }

    /**
     * Append a hidden element to a form.
     *
     * @param string $name
     * @param string $value
     * @return \DOMElement
     */
    public function appendHiddenElement($name, $value)
    {
        if ($this->form != null) {
            $nl = $this->form->ownerDocument->createTextNode("\n");
            $node = $this->form->ownerDocument->createElement('input');
            $node->setAttribute('type', 'hidden');
            $node->setAttribute('name', $name);
            $node->setAttribute('value', Template::objectToString($value));
            $this->form->appendChild($node);
            $this->form->appendChild($nl);
            $this->elements[$name][] = $node;
        }
        return $node;
    }

    /**
     * Get an array of the hidden elements in this form
     *
     * @return Input[]
     */
    public function getHiddenElements()
    {
        $arr = array();
        /** @var \DOMElement $element */
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
     *
     * @return string
     */
    public function getName()
    {
        if ($this->form != null) {
            return $this->form->getAttribute('name');
        }
        return '';
    }

    /**
     * Get the form id attribute
     *
     * @return string
     */
    public function getId()
    {
        if ($this->form != null) {
            return $this->form->getAttribute('id');
        }
        return '';
    }

    /**
     * Get the \DOMElement of this form object.
     *
     * @return \DOMElement
     */
    public function getNode()
    {
        return $this->form;
    }

    /**
     * Get the parent template for this form
     *
     * @return Template
     */
    public function getTemplate()
    {
        return $this->parent;
    }
}

