<?php
namespace Dom\Form;

/**
 * A class that handle a forms select element.
 *
 * @author Tropotek <http://www.tropotek.com/>
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
     * @note Ensure no comment nodes are in the selects node tree.
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
    public function setValue(string|array $value): static
    {
        if (is_array($value)) {
            if ($this->isMultiple()) {
                foreach ($value as $v) {
                    $option = $this->findOption($this->element, $v);
                    if ($option instanceof \DOMElement) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            } else {
                $option = $this->findOption($this->element, $value[0]);
                if ($option instanceof \DOMElement) {
                    $option->setAttribute('selected', 'selected');
                }
            }
        } else {
            if (!$this->isMultiple()) {
                $this->clearSelected();
            }
            $option = $this->findOption($this->element, $value);
            if ($option instanceof \DOMElement) {
                $option->setAttribute('selected', 'selected');
            }
        }
        return $this;
    }

    /**
     * Return the selected value,
     * Will return an array if multiple select is enabled.
     */
    public function getValue(): string|array
    {
        $selected = $this->findSelected($this->element);
        if (is_array($selected) && count($selected) > 0) {
            if ($this->isMultiple()) {
                return array_map(fn($r) => $r->textContent, $selected);
            } else {
                return $selected[0]->textContent;
            }
        }
        return '';
    }

    public function removeOptions(): self
    {
        while ($this->element != null && $this->element->hasChildNodes()) {
            $this->element->removeChild($this->element->childNodes->item(0));
        }
        return $this;
    }

    public function clearSelected(): self
    {
        $this->clearSelectedFunction($this->element);
        return $this;
    }

    private function clearSelectedFunction(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            if ($node->nodeName == 'option' && $node->hasAttribute('selected')) {
                $node->removeAttribute('selected');
            }
            foreach ($node->childNodes as $child) {
                $this->clearSelectedFunction($child);
            }
        }
    }

    public function findOptGroup(\DOMNode $node, string $name): ?\DOMNode
    {
        $foundNode = null;
        if ($node instanceof \DOMElement) {
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

    public function findOption(\DOMNode $node, string $value): ?\DOMNode
    {
        $foundNode = null;
        if ($node instanceof \DOMElement) {
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
     */
    public function findSelected(\DOMNode $node): \DOMNode|array
    {
        $foundNodes = array();
        if ($node instanceof \DOMElement) {
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

    public function optGroupExists(string $name): bool
    {
        return $this->findOptGroup($this->element, $name) != null;
    }

    /**
     * Set the select list to handle multiple selections
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
     * Returns true if multiple selects are allowed
     */
    public function isMultiple(): bool
    {
        return $this->element->hasAttribute('multiple');
    }
}
