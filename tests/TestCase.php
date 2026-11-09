<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Parser;
use Tk\DomTemplate\Template;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        // Parser is a shared singleton, so don't let registered parsers leak between tests
        Parser::instance()->clear();
    }

    /**
     * Render a template and return an XPath over the output
     */
    protected function render(Template $template): \DOMXPath
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $template->toString());
        libxml_clear_errors();
        return new \DOMXPath($doc);
    }

    /**
     * Text of every node matching an XPath query
     * @return string[]
     */
    protected function texts(\DOMXPath $xp, string $query): array
    {
        $out = [];
        foreach ($xp->query($query) ?: [] as $node) {
            $out[] = trim($node->textContent);
        }
        return $out;
    }
}
