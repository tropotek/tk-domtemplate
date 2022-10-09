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
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Input extends Element
{

    /**
     * Set the checked attribute of an element
     */
    public function setChecked(bool $b): Input
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
     */
    public function isChecked(): bool
    {
        return $this->element->hasAttribute('checked');
    }

    /**
     * Set the value of this form element.
     */
    public function setValue($value): Input
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
     */
    public function getValue()
    {
        return $this->element->getAttribute('value');
    }

}