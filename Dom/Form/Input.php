<?php
namespace Dom\Form;

/**
 * A class that handle a forms input element.
 *
 * @author Tropotek <http://www.tropotek.com/>
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

    public function setValue(string|array $value): Input
    {
        if (is_array($value)) $value = strval($value[0]);

        if ($this->getType() == 'checkbox' || $this->getType() == 'radio') {
            $this->form->setCheckedByValue($this->getName(), $value);
        } else {
            $this->element->setAttribute('value', $value);
        }
        return $this;
    }

    public function getValue(): array|string
    {
        return $this->element->getAttribute('value');
    }

}