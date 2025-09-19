<?php

namespace Dom\Parser;

use Dom\Template;

/**
 * The interface for all DomTemplate Parser objects
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class ParserInterface
{
    protected Template $template;

    public function __construct(Template $template)
    {
        $this->template = $template;
    }

    public abstract function prepareDoc(\DOMNode $node, string $form = ''): void;

    public abstract function preParse(): void;

    public abstract function postParse(): void;

    public function getTemplate(): Template
    {
        return $this->template;
    }

}