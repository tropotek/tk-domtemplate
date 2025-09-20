<?php
namespace Dom\Form;

/**
 * Handle rendering of a form input element.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Input extends Element
{

    /**
     * @param string|array<int,string> $value
     */
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

    /**
     * @return array<string,string>
     */
    public function getValue(): array|string
    {
        return $this->element->getAttribute('value');
    }

    public function setChecked(bool $b): Input
    {
        if ($b) {
            $this->element->setAttribute('checked', 'checked');
        } else {
            $this->element->removeAttribute('checked');
        }
        return $this;
    }

    public function isChecked(): bool
    {
        return $this->element->hasAttribute('checked');
    }

}