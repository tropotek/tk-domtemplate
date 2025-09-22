<?php

namespace Dom\Parser;

use Dom\Parser;
use Dom\Template;

/**
 * The interface for all DomTemplate Parser objects
 *s
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class ParserInterface
{

    public function __construct() { }

    public abstract function prepare(\DOMNode $node, string $form = ''): void;

    public abstract function preParse(): void;

    public abstract function postParse(): void;

    public function getTemplate(): Template
    {
        return Parser::instance()->getTemplate();
    }

}