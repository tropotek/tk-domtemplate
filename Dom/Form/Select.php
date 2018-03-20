<?php
namespace Dom\Form;

use Dom\Form;
use Dom\Template;

/**
 * A class that handle a forms select element.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @license Copyright 2007
 */
class Select extends Element
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
     * @param string $text The text shown in the dropdown
     * @param string $value The value for the select option
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
            $option->setAttribute('value', $value);
        }

        if ($this->useTextNode) {
            $text_el = $doc->createTextNode($text);
            $option->appendChild($text_el);
        } else {
            $text = preg_replace('/&( )/', '&amp; ', $text);
            $option->nodeValue = $text;
        }

        $optGroupNode = null;
        if ($optGroup) {
            $optGroupNode = $this->findOptGroup($this->element, $optGroup);
            if (!$optGroupNode) {
                $optGroupNode = $this->appendOptGroup($optGroup);
            }
        }
        if ($optGroupNode) {
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
     * @param string $label The label for the optGroup
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
        if ($optGroup) {
            $optGroupNode = $this->findOptGroup($this->element, $optGroup);
        }
        if ($optGroupNode) {
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
     * @param string|array $value A string for single, an array for multiple
     * @return $this
     */
    public function setValue($value)
    {
        if (is_array($value)) {
            if ($this->isMultiple()) {
                foreach ($value as $v) {
                    $option = $this->findOption($this->element, $v);
                    if ($option) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            } else {
                $option = $this->findOption($this->element, $value[0]);
                if ($option) {
                    $option->setAttribute('selected', 'selected');
                }
            }
        } else {
            if (!$this->isMultiple()) {
                $this->clearSelected();
            }
            $option = $this->findOption($this->element, $value);
            if ($option) {
                $option->setAttribute('selected', 'selected');
            }
        }
        return $this;
    }

    /**
     * Return the selected value,
     * Will return an array if  multiple select is enabled.
     *
     * @return \DOMNode|\DOMNode[] Returns null if nothing selected.
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
     * @return $this
     */
    public function removeOptions()
    {
        while ($this->element != null && $this->element->hasChildNodes()) {
            $this->element->removeChild($this->element->childNodes->item(0));
        }
        return $this;
    }

    /**
     * Clear all selected elements
     *
     * @return $this
     */
    public function clearSelected()
    {
        $this->clearSelectedFunction($this->element);
        return $this;
    }

    /**
     * Find the opt group node with the name
     *
     * @param \DOMElement $node
     * @return \DOMElement|Select
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
        return $this;
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
     * @return \DOMElement|\DOMElement[]
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
     * <b>NOTE:</b> When multiple is disabled and multiple elements are selected
     *  it behaviour is unknown and browser specific.
     *
     * @param bool $b
     * @return $this
     */
    public function enableMultiple($b)
    {
        if ($b) {
            $this->element->setAttribute('multiple', 'multiple');
        } else {
            $this->element->removeAttribute('multiple');
        }
        return $this;
    }

    /**
     * Return if this is a multiple select or not.
     *
     * @return bool Returns true if multiple selects are allowed
     */
    public function isMultiple()
    {
        return $this->element->hasAttribute('multiple');
    }
}
