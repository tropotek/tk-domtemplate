<?php

namespace Dom\Parser;

use Dom\Form\Form;

class FormParser extends ParserInterface
{
    public static array $FORM_ELEMENT_NODES = ['input', 'textarea', 'select', 'button'];

    /**
     * @var array<string,\DOMElement>
     */
    protected array $form = [];

    /**
     * @var array<string,array<string,array<int,\DOMElement>>>
     */
    protected array $formElement = [];

    protected string $currFormId = '';


    public function prepare(\DOMNode $node, string $form = ''): void
    {
        // Store all Form nodes
        if ($node->nodeName == 'form') {
            $this->currFormId = strval($node->getAttribute('id') ?? $node->getAttribute('name') ?? '');
            if (!$this->currFormId) {
                $this->currFormId = 'form-' . count($this->form);
            }
            $this->formElement[$this->currFormId] = [];
            $this->form[$this->currFormId] = $node;
        }

        // Store all FormElement nodes
        if (in_array($node->nodeName, self::$FORM_ELEMENT_NODES)) {
            $name = $node->getAttribute('name');
            if ($name == null && $node->getAttribute('id')) {
                $name = $node->getAttribute('id');
            }

            $this->formElement[$this->currFormId][$name][] = $node;
            if (!isset($this->form[$this->currFormId]) && $form == '') $this->form[$this->currFormId] = $this->getTemplate()->getDocument(false)->documentElement;
        }

    }

    public function preParse(): void { }

    public function postParse(): void { }


    /**
     * Return a form object from the document.
     */
    public function getForm(string $id = ''): ?Form
    {
        if (!$this->getTemplate()->isParsed() && isset($this->form[$id])) {
            return new Form($this->form[$id], $this->formElement[$id], $this->getTemplate());
        }
        return null;
    }
}