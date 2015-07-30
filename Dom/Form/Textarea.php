<?php
/*
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
 */
namespace Dom\Form;

use Dom\Form;
use Dom\Template;


/**
 * A class that handles a forms textarea element.
 *
 *
 */
class Textarea extends Element
{

    /**
     * Set the value of this form element
     *
     * @param string $value
     * @return Textarea
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