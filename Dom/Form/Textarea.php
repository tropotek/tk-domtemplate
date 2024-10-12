<?php
namespace Dom\Form;

use Dom\Form;
use Dom\Template;


/**
 * A class that handles a forms textarea element.
 *
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Textarea extends Element
{

    /**
     * Set the value of this form element
     */
    public function setValue(string|array $value): Textarea
    {
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