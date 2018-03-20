<?php
namespace Dom\Form;

use Dom\Form;
use Dom\Template;


/**
 * A class that handle a forms input element.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @license Copyright 2007
 */
class Input extends Element
{

    /**
     * Set the checked attribute of an element
     *
     * @param bool $b
     * @return $this
     */
    public function setChecked($b)
    {
        if ($b) {
            $this->element->setAttribute('checked', 'checked');
        } else {
            $this->element->removeAttribute('checked');
        }
        return $this;
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
     * @return Input
     */
    public function setValue($value)
    {
        if ($this->getType() == 'checkbox' || $this->getType() == 'radio') {
            $this->form->setCheckedByValue($this->getName(), $value);
        } else {
            $this->element->setAttribute('value', $value);
        }
        return $this;
    }

    /**
     * Return the value of this form element
     *
     * @return string
     */
    public function getValue()
    {
        return $this->element->getAttribute('value');
    }

}