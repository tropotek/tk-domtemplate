<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Html;
use Tk\DomTemplate\Kind;
use Tk\DomTemplate\Scope;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes from the phase 1 review
 */
class FoundationFixTest extends TestCase
{
    private function lenient(): Engine
    {
        return new Engine(strict: false, logger: new SpyLogger());
    }

    public function testLenientConflictingOptionalIsStillRemoved(): void
    {
        $this->assertSame('<div><p>a</p></div>', $this->lenient()->load('<div><p var="x">a</p><p var="x?">SECRET</p></div>')->render());
    }

    public function testLenientMalformedOptionalIsStillRemoved(): void
    {
        $this->assertSame('<div></div>', $this->lenient()->load('<div><p var="is admin?">SECRET</p></div>')->render());
    }

    public function testLenientSecondSampleIsStillRemoved(): void
    {
        $this->assertSame('<ul></ul>', $this->lenient()->load('<ul><li var="r[]">a</li><li var="r[]">SAMPLE2</li></ul>')->render());
    }

    public function testVarInsideTemplateElementIsReported(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('inside <template>');
        Template::load('<div><template x-if="o"><p var="admin?">SECRET</p></template></div>');
    }

    public function testFragmentStartingWithCommentKeepsRows(): void
    {
        $this->assertSame('<!-- row --><tr><td>1</td></tr>', Template::load('<!-- row --><tr><td>1</td></tr>')->render());
    }

    public function testBomIsStripped(): void
    {
        $page = Template::load("\xEF\xBB\xBF<!doctype html>\n<html><head><title>x</title></head><body><p>a</p></body></html>");
        $this->assertFalse($page->isFragment());
        $this->assertStringStartsWith('<!DOCTYPE html>', $page->render());
        $this->assertSame('<p>a</p>', Template::load("\xEF\xBB\xBF<p>a</p>")->render());
    }

    public function testRecursiveRepeatWithSameNameBuildsRowScope(): void
    {
        $root = Html::parseFragment(\Dom\HTMLDocument::createEmpty(), '<li var="item[]"><a var="link"></a><ul><li var="item[]"></li></ul></li>');
        $sample = $root->firstElementChild;
        $this->assertNotNull($sample);
        $row = Scope::build($sample, 'x', fn(string $m) => throw new TemplateException($m));
        $this->assertSame(Kind::Repeat, $row->kind('item'));
        $this->assertSame(Kind::Var, $row->kind('link'));
    }

    public function testTableAndSelectFragmentsThroughTemplate(): void
    {
        $this->assertSame('<tr><td>1</td></tr><tr><td>2</td></tr>', Template::load('<tr var="a"><td>1</td></tr><tr><td>2</td></tr>')->render());
        $this->assertSame('<option>a</option><option>b</option>', Template::load('<option var="o">a</option><option>b</option>')->render());
    }

    public function testAlpineAndEncodingOnFullPage(): void
    {
        $html = "<!DOCTYPE html><html><head></head><body><div x-data=\"{o:1}\" @click=\"o++\" hx-get=\"/x\">é 日本 😀&nbsp;</div><template x-for=\"i in l\"><b x-text=\"i\"></b></template></body></html>";
        $this->assertSame($html, Template::load($html)->render());
    }
}
