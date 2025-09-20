<?php
namespace Dom\Form;

/**
 * Handle rendering of a form textarea element.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Textarea extends Element
{

    public function setValue(string|array $value): Textarea
    {
        if (is_array($value)) $value = strval($value[0]);

        $dom = $this->element->ownerDocument;
        $textNode = $dom->createTextNode($value);
        $this->element->appendChild($textNode);
        return $this;
    }

    public function getValue(): string
    {
        return strval($this->element->nodeValue);
    }
}