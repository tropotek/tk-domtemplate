<?php
namespace Tk\DomTemplate\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Tk\DomTemplate\Html;

class HtmlTest extends TestCase
{
    public function testFullDocumentDetection(): void
    {
        $this->assertTrue(Html::isFullDocument("<!doctype html>\n<html><body></body></html>"));
        $this->assertTrue(Html::isFullDocument("  <!-- c -->\n<html lang=\"en\"><body></body></html>"));
        $this->assertFalse(Html::isFullDocument('<div>hi</div>'));
        $this->assertFalse(Html::isFullDocument('<htmlish>x</htmlish>'));
    }

    public function testContextFor(): void
    {
        $this->assertSame('tbody', Html::contextFor('TR'));
        $this->assertSame('tr', Html::contextFor('td'));
        $this->assertSame('tr', Html::contextFor('th'));
        $this->assertSame('table', Html::contextFor('tbody'));
        $this->assertSame('colgroup', Html::contextFor('col'));
        $this->assertSame('select', Html::contextFor('option'));
        $this->assertSame('select', Html::contextFor('optgroup'));
        $this->assertSame('div', Html::contextFor('li'));
    }

    #[DataProvider('fragments')]
    public function testFragmentKeepsEveryNode(string $html): void
    {
        $doc = \Dom\HTMLDocument::createEmpty();
        $this->assertSame($html, Html::parseFragment($doc, $html)->innerHTML);
    }

    /**
     * @return list<array{string}>
     */
    public static function fragments(): array
    {
        return [
            ['<tr><td>1</td></tr><tr><td>2</td></tr>'],
            ['<td>a</td><td>b</td>'],
            ['<option value="1">One</option><optgroup label="g"><option>Two</option></optgroup>'],
            ['text before <b>bold</b> after'],
            ['<p>a</p><p>b</p>'],
            ['<template x-for="i in items"><li x-text="i"></li></template>'],
        ];
    }
}
