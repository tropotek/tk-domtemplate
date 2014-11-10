<?php
/*
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
 */
namespace Dom;


/**
 * The form package make an API available for rendering a form and its elements
 *
 * The form package currently does not fully support element arrays.
 * It can be done but it is not fully supported or tested.
 *
 * @package \Dom
 */
class Form
{

    /**
     * @var \DOMElement
     */
    protected $form = null;

    /**
     * An Array of \Dom\FormElement objects
     * @var \Dom\FormElement[]
     */
    protected $elements = array();

    /**
     * @var \Dom\Template
     */
    protected $parent = null;

    /**
     * __construct
     *
     * @param DOMElement $form
     * @param array $element An array of form elements
     * @param \Dom_Template $parent The parent object
     */
    public function __construct($form, $elements, $parent)
    {
        $this->form = $form;
        $this->parent = $parent;
        $this->elements = $elements;
    }

    /**
     * Set/unset the checkboxes and radioboxes.
     * <b>NOTE:</b> This is called by \Dom_FormInputElement<br>
     *   $value is not required for checkboxes
     *
     * @param string $name
     * @param string $value
     */
    public function setCheckedByValue($name, $value = '')
    {
        if (!isset($this->elements[$name])) {
            return;
        }
        $elements = $this->elements[$name];
        foreach ($elements as $element) {
            if ($value !== null && ($element->getAttribute('value') == $value)) {
                $element->setAttribute('checked', 'checked');
            } else if ($element->getAttribute('type') == 'radio') {
                $element->removeAttribute('checked');
            }
        }
    }

    /**
     * Return the form element with the name.
     *
     * @param string $name
     * @param int $i optioinal index for multiple elements
     * @return \Dom\FormElement
     */
    public function getFormElement($name, $i = 0)
    {
        if (!$this->elementExists($name)) {
            return null;
        }
        $element = $this->elements[$name][$i];
        $type = $element->nodeName;
        if ($type == 'input') {
            return new FormInput($element, $this);
        } elseif ($type == 'textarea') {
            return new FormTextarea($element, $this);
        } elseif ($type == 'select') {
            return new FormSelect($element, $this);
        }
        return null;
    }

    /**
     * Get an array of form elements with the name value
     * Used for radioboxes and multi select lists
     *
     * @param string $name
     * @return array
     */
    public function getFormElementList($name)
    {
        if (!$this->elementExists($name)) {
            return array();
        }
        $nodeList = array();
        $n = count($this->elements[$name]);
        for($i = 0; $i < $n; $i++) {
            $element = $this->elements[$name][$i];
            $type = $element->nodeName;
            if ($type == 'input') {
                $nodeList[] = new FormInput($element, $this);
            } else {
                if ($type == 'textarea') {
                    $nodeList[] = new FormTextarea($element, $this);
                } else {
                    if ($type == 'select') {
                        $nodeList[] = new FormSelect($element, $this);
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
     * @param string $action
     */
    public function setAction($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('action', Template::objectToString($value));
        }
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
     * @param string $method
     */
    public function setMethod($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('method', Template::objectToString($value));
        }
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
     * @param string $target
     */
    public function setTarget($value)
    {
        if ($this->form != null) {
            $this->form->setAttribute('target', Template::objectToString($value));
        }
    }

    /**
     * Append a hidden element to a form.
     *
     * @param string $name
     * @param string $value
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
        }
    }

    /**
     * Get an array of the hidden elements in this form
     *
     * @return \Dom\FormInputElement[]
     */
    public function getHiddenElements()
    {
        $arr = array();
        foreach ($this->elements as $element) {
            $type = $element->nodeName;
            $inputType = $element->getAttribute('type');
            if ($type == 'input' && $inputType == 'hidden') {
                $arr[] = new FormInputElement($element, $this);
            }
        }
        return $arr;
    }

    /**
     * Get the form name.
     *
     * @return string
     * @deprecated HTML5 this is no-longer valid XHTML. use getId()
     */
    public function getName()
    {
        if ($this->form != null) {
            return $this->form->getAttribute('name');
        }
        return '';
    }

    /**
     * Get the note id attribute value if available
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
     * Check if a repeat,choice,var,form (template property) exists.
     * @param string $property
     * @param string $key
     * @return bool
     */
    private function elementExists($key)
    {
        if (!array_key_exists($key, $this->elements)) {
            return false;
        }
        return true;
    }

    /**
     * Get the parent template for this form
     *
     * @return \Dom\Template
     */
    public function getTemplate()
    {
        return $this->parent;
    }
}

/**
 * All form elements must use this class/interface.
 *
 * @package \Dom
 */
abstract class FormElement
{

    /**
     * This could be a single \DOMElement or an array of \DOMElement
     * @var \DOMElement
     */
    protected $element = null;

    /**
     * @var \Dom\Form
     */
    protected $form = null;

    /**
     * __construct
     *
     * @param \DOMElement $element
     * @param \Dom\Form $form
     */
    public function __construct($element, $form = null)
    {
        $this->element = $element;
        $this->form = $form;
    }

    /**
     * Set the name of this element
     *
     * @param string name
     */
    public function setName($name)
    {
        $this->element->setAttribute('name', $name);
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
     * @return \Dom\Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Get the Element's Template
     *
     * @return \Dom\Template
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
     * @return \Dom\FormElement
     */
    abstract function setValue($value);

    /**
     * Return the value of the element, or the selected value.
     *
     * @return mixed A string or an array of strings for
     *    multiple select elements
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
     * Disable this allement, add a disable attribute to the node
     */
    public function disable()
    {
        $this->element->setAttribute('disabled', 'disabled');
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
     * @param string name
     * @param string value
     */
    public function setAttribute($name, $value)
    {
        $this->element->setAttribute($name, $value);
    }

    /**
     * Set the name of this element
     * @return string
     */
    public function getAttribute($name)
    {
        return $this->element->getAttribute($name);
    }
}

/**
 * A class that handle a forms input element.
 *
 * @package \Dom
 */
class FormInput extends FormElement
{

    /**
     * Set the checked attribute of an element
     *
     * @param bool $b
     */
    public function setChecked($b)
    {
        if ($b) {
            $this->element->setAttribute('checked', 'checked');
        } else {
            $this->element->removeAttribute('checked');
        }
    }

    /**
     * Get the checked state of this element
     *
     * @return bool
     */
    public function isChecked()
    {
        return $this->element->hasAttribute('checked');
    }

    /**
     * Set the value of this form element.
     *
     * @param string $value
     * @return \Dom\FormInput
     */
    public function setValue($value)
    {
        if ($this->getType() == 'checkbox' || $this->getType() == 'radio') {
            $this->form->setCheckedByValue($this->getName(), Template::objectToString($value));
        } else {
            $this->element->setAttribute('value', Template::objectToString($value));
        }
        return $this;
    }

    /**
     * Return the value of theis form element
     *
     * @return string
     */
    public function getValue()
    {
        return $this->element->getAttribute('value');
    }

}

/**
 * A class that handles a forms textarea element.
 *
 * @package \Dom
 */
class FormTextarea extends FormElement
{

    /**
     * Set the value of this form element
     *
     * @param string $value
     * @return \Dom\FormTextarea
     */
    public function setValue($value)
    {
        $dom = $this->element->ownerDocument;
        $textNode = $dom->createTextNode($value);
        $this->element->appendChild($textNode);
        return $this;
    }

    /**
     * Get the current text in the textarea
     *
     * @return string
     */
    public function getValue()
    {
        return $this->element->nodeValue;
    }
}

/**
 * A class that handle a forms select element.
 *
 * @package \Dom
 */
class FormSelect extends FormElement
{

    /**
     * set to false to add spaces (&#160; or &nbsp;)
     * @var bool
     */
    private $useTextNode = true;

    /**
     * Use Text Nodes, Set to True by default
     *
     * @param bool $b
     */
    public function useTextNodes($b)
    {
        $this->useTextNode = $b;
    }

    /**
     * Append an 'Option' to this 'Select' object
     *
     * If no value is supplied the text parameter is used as the value.
     *
     * NOTE: Ensure no comment nodes are in the select's node tree.
     * @param string The text shown in the dropdown
     * @param string The value for the select option
     * @param string $optGroup Use this optgroup if it exists
     * @return \DOMElement
     */
    public function appendOption($text, $value = null, $optGroup = '')
    {
        $doc = $this->element->ownerDocument;
        $nl = $doc->createTextNode("\n");
        $option = $doc->createElement('option');
        if ($value === null) {
            $option->setAttribute('value', $text);
        } else {
            $option->setAttribute('value', Template::objectToString($value));
        }

        if ($this->useTextNode) {
            $text_el = $doc->createTextNode($text);
            $option->appendChild($text_el);
        } else {
            $text = preg_replace('/&( )/', '&amp; ', $text);
            $option->nodeValue = $text;
        }

        $optGroupNode = null;
        if ($optGroup != null) {
            $optGroupNode = $this->findOptGroup($this->element, $optGroup);
        }
        if ($optGroupNode != null) {
            $optGroupNode->appendChild($nl);
            $optGroupNode->appendChild($option);
        } else {
            $this->element->appendChild($nl);
            $this->element->appendChild($option);
        }

        return $option;
    }

    /**
     * Append an 'OptGroup' to the base node or the optGroup
     *
     *
     * @param string The label for the optGroup
     * @param string $optGroup Append to this optgroup if it exists
     * @return \DOMElement
     */
    public function appendOptGroup($label, $optGroup = '')
    {
        $doc = $this->element->ownerDocument;
        $nl = $doc->createTextNode("\n");
        $option = $doc->createElement('optgroup');

        $option->setAttribute('label', $label);

        $optGroupNode = null;
        if ($optGroup != null) {
            $optGroupNode = $this->findOptGroup($this->element, $optGroup);
        }
        if ($optGroupNode != null) {
            $optGroupNode->appendChild($nl);
            $optGroupNode->appendChild($option);
        } else {
            $this->element->appendChild($nl);
            $this->element->appendChild($option);
        }
        return $option;
    }

    /**
     * Set the selected value of the form element
     *
     * @param mixed $value A string for single, an array for multiple
     * @return \Dom\FormSelect
     */
    public function setValue($value)
    {
        if (is_array($value)) {
            if ($this->isMultiple()) {
                foreach ($value as $v) {
                    $option = $this->findOption($this->element, $v);
                    if ($option != null) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            } else {
                $option = $this->findOption($this->element, $value[0]);
                if ($option != null) {
                    $option->setAttribute('selected', 'selected');
                }
            }
        } else {
            if (!$this->isMultiple()) {
                $this->clearSelected();
            }
            $option = $this->findOption($this->element, $value);
            if ($option != null) {
                $option->setAttribute('selected', 'selected');
            }
        }
        return $this;
    }

    /**
     * Return the selected value,
     * Will return an array if  multiple select is enabled.
     *
     * @return \DOMNode Returns null if nothing selected.
     */
    public function getValue()
    {
        $selected = $this->findSelected($this->element);
        if (count($selected) > 0) {
            if ($this->isMultiple()) {
                return $selected;
            } else {
                return $selected[0];
            }
        }
        return null;
    }

    /**
     * Clear this 'select' element of all its 'option' elements.
     *
     */
    public function removeOptions()
    {
        while ($this->element != null && $this->element->hasChildNodes()) {
            $this->element->removeChild($this->element->childNodes->item(0));
        }
    }

    /**
     * Clear all selected elements
     *
     */
    public function clearSelected()
    {
        $this->clearSelectedFunction($this->element);
    }

    /**
     * Find the opt group node with the name
     *
     * @param \DOMElement $node
     * @param string $name
     * @return \DOMElement
     */
    private function clearSelectedFunction($node)
    {
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'option' && $node->hasAttribute('selected')) {
                $node->removeAttribute('selected');
            }
            foreach ($node->childNodes as $child) {
                $this->clearSelectedFunction($child);
            }
        }
    }

    /**
     * Find the opt group node with the name
     *
     * @param \DOMElement $node
     * @param string $name
     * @return \DOMElement
     */
    public function findOptGroup($node, $name)
    {
        $foundNode = null;
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'optgroup' && $node->getAttribute('label') == $name) {
                return $node;
            }
            foreach ($node->childNodes as $child) {
                $fNode = $this->findOptGroup($child, $name);
                if ($fNode != null) {
                    $foundNode = $fNode;
                }
            }
        }
        return $foundNode;
    }

    /**
     * Find an option node
     *
     * @param \DOMElement $node
     * @param string $value
     * @return \DOMElement
     */
    public function findOption($node, $value)
    {
        $foundNode = null;
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'option' && $node->getAttribute('value') == $value) {
                return $node;
            }
            foreach ($node->childNodes as $child) {
                $fNode = $this->findOption($child, $value);
                if ($fNode != null) {
                    $foundNode = $fNode;
                }
            }
        }
        return $foundNode;
    }

    /**
     * Find the selected values to this select box
     *
     * @param \DOMElement $node
     * @return array
     */
    public function findSelected($node)
    {
        $foundNodes = array();
        if ($node->nodeType == XML_ELEMENT_NODE) {
            if ($node->nodeName == 'option' && $node->hasAttribute('selected')) {
                return $node;
            }
            foreach ($node->childNodes as $child) {
                $fNode = $this->findSelected($child);
                if ($fNode != null) {
                    $foundNodes[] = $fNode;
                }
            }
        }
        return $foundNodes;
    }

    /**
     * Check if the opt group exists
     *
     * @param string $name
     * @return bool
     */
    public function optGroupExists($name)
    {
        return $this->findOptGroup($this->element, $name) != null;
    }

    /**
     * Set the select list to handle multiple selections
     * <b>NOTE:</b> When multiple is dissabled and multiple elements are selected
     *  it behaviour is unknown and browser specific.
     *
     * @param bool $b
     */
    public function enableMultiple($b)
    {
        if ($b) {
            $this->element->setAttribute('multiple', 'multiple');
        } else {
            $this->element->removeAttribute('multiple');
        }
    }

    /**
     * Return if this is a multiple select or not.
     *
     * @return bool Returns true if muliple selects are allowed
     */
    public function isMultiple()
    {
        return $this->element->hasAttribute('multiple');
    }
}
