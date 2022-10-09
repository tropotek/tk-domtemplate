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
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Select extends Element
{

    /**
     * If set to true then option text will be added using createTextNode()
     * Else the text will be set to the nodeValue param and & will be escaped to &amp;
     */
    public static bool $OPTIONS_USE_TEXT_NODE = true;


    /**
     * Append an 'Option' to this 'Select' object
     *
     * If no value is supplied the text parameter is used as the value.
     *
     * @note Ensure no comment nodes are in the select's node tree.
     */
    public function appendOption(string $text, ?string $value = null, string $optGroup = ''): \DOMElement
    {
        $doc = $this->element->ownerDocument;
        $nl = $doc->createTextNode("\n");
        $option = $doc->createElement('option');
        if ($value === null) {
            $option->setAttribute('value', $text);
        } else {
            $option->setAttribute('value', $value);
        }

        if (self::$OPTIONS_USE_TEXT_NODE) {
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
     */
    public function appendOptGroup(string $label, string $optGroup = ''): \DOMElement
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
     */
    public function setValue($value): Select
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
     * Will return an array if multiple select is enabled.
     *
     * @return string|array
     */
    public function getValue()
    {
        $selected = $this->findSelected($this->element);
        if (count($selected) > 0) {
            if ($this->isMultiple()) {
                return array_map(fn($r) => $r->textContent, $selected);
            } else {
                return $selected[0]->textContent;
            }
        }
        return null;
    }

    /**
     * Clear this 'select' element of all its 'option' elements.
     */
    public function removeOptions(): Select
    {
        while ($this->element != null && $this->element->hasChildNodes()) {
            $this->element->removeChild($this->element->childNodes->item(0));
        }
        return $this;
    }

    /**
     * Clear all selected elements
     */
    public function clearSelected(): Select
    {
        $this->clearSelectedFunction($this->element);
        return $this;
    }

    /**
     * Find the option group node with the name
     */
    private function clearSelectedFunction(\DOMNode $node): void
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
     * Find the option group node with the name
     */
    public function findOptGroup(\DOMNode $node, string $name): ?\DOMNode
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
     */
    public function findOption(\DOMNode $node, string $value): ?\DOMNode
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
     * @param \DOMNode $node
     * @return \DOMNode|\DOMNode[]
     */
    public function findSelected(\DOMNode $node)
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
     */
    public function optGroupExists(string $name): bool
    {
        return $this->findOptGroup($this->element, $name) != null;
    }

    /**
     * Set the select list to handle multiple selections
     * <b>NOTE:</b> When multiple is disabled and multiple elements are selected
     *  it behaviour is unknown and browser specific.
     */
    public function enableMultiple(bool $b): Select
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
     * Returns true if multiple selects are allowed
     */
    public function isMultiple(): bool
    {
        return $this->element->hasAttribute('multiple');
    }
}
