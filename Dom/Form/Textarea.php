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
     *
     * @param string $value
     */
    public function setValue($value): Textarea
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