<?php
namespace Dom\Form;

/**
 * A class that handles a forms textarea element.
 *
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Textarea extends Element
{

    /**
     * Set the value of this form element
     */
    public function setValue(string|array $value): Textarea
    {
        if (is_array($value)) $value = strval($value[0]);

        $dom = $this->element->ownerDocument;
        $textNode = $dom->createTextNode($value);
        $this->element->appendChild($textNode);
        return $this;
    }

    /**
     * Get the current text in the textarea
     */
    public function getValue(): string
    {
        return strval($this->element->nodeValue);
    }
}