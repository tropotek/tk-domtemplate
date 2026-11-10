<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Place;
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

/**
 * Fixes from the phase 5 review
 */
class AssetFixTest extends TestCase
{
    private function page(string $body): Template
    {
        return Template::load("<!doctype html>\n<html><head><title>Page</title></head><body>$body</body></html>");
    }

    public function testRowsInsideAHiddenContainerContributeNoAssets(): void
    {
        $page = $this->page('<div var="box?"><ul><li var="r[]">x</li></ul></div>');
        $page->repeat('r')->cssUrl('/row.css')->js('rowEnd();', Place::End)->meta('robots', 'none')->title('RowTitle')->append();
        $out = $page->render();
        foreach (['/row.css', 'rowEnd();', 'robots', 'RowTitle'] as $leak) {
            $this->assertStringNotContainsString($leak, $out);
        }
        $this->assertStringContainsString('<title>Page</title>', $out);
    }

    public function testNestedRowInsideAHiddenRowVarContributesNoAssets(): void
    {
        $page = $this->page('<ul><li var="r[]"><div var="inner?"><b var="n[]"></b></div></li></ul>');
        $row = $page->repeat('r');
        $row->repeat('n')->cssUrl('/nested.css')->append();
        $row->append();
        $this->assertStringNotContainsString('/nested.css', $page->render());
    }

    public function testRowsInAnOverwrittenContainerContributeNoAssets(): void
    {
        $page = $this->page('<div var="box"><ul><li var="r[]">x</li></ul></div>');
        $page->repeat('r')->cssUrl('/gone.css')->append();
        $page->html('box', 'replaced');
        $this->assertStringNotContainsString('/gone.css', $page->render());
    }

    public function testVisibleRowAssetsStillBubble(): void
    {
        $page = $this->page('<ul><li var="r[]">x</li></ul>');
        $page->repeat('r')->cssUrl('/row.css')->append();
        $this->assertMatchesRegularExpression('#<head>.*/row\.css.*</head>#s', $page->render());
    }

    public function testNestedRowInlineAssetsInEveryAppendToCopy(): void
    {
        $page = Template::load('<div><ul var="l"></ul><ul var="l"></ul><li var="r[]"><b var="n[]"></b></li></div>');
        $row = $page->repeat('r');
        $row->repeat('n')->js('nested();')->append();
        $row->appendTo('l');
        $this->assertSame(2, substr_count($page->render(), 'nested();'));
    }

    public function testKeyedAssetInChildSharedByTwoPages(): void
    {
        $child = Template::load('<p>c</p>')->js('k();', key: 'k');
        $a = Template::load('<div><i var="x"></i><i var="y"></i></div>');
        $a->js('k();', key: 'k')->html('x', $child);
        $b = Template::load('<div var="z"></div>');
        $b->html('z', $child);
        $this->assertSame(1, substr_count($a->render(), 'k();'));
        $this->assertSame(1, substr_count($b->render(), 'k();'));
    }

    public function testKeyedAssetWrittenOnceAcrossTwoInsertsOfOneChild(): void
    {
        $child = Template::load('<p>c</p>')->js('k();', key: 'k');
        $page = Template::load('<div><i var="x"></i><i var="y"></i></div>');
        $page->html('x', $child)->html('y', $child);
        $this->assertSame(1, substr_count($page->render(), 'k();'));
        $this->assertStringNotContainsString('tk-key', $page->render());
    }

    public function testScriptAndStyleCannotBeClosedFromInside(): void
    {
        $t = Template::load('<div>x</div>')->js('var s = "</script><img src=x onerror=alert(1)>";')->css('a::after{content:"</STYLE><b>"}');
        $out = $t->render();
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><html><body>' . $out . '</body></html>', LIBXML_NOERROR);
        $this->assertNull($doc->querySelector('img'), 'script text must not create elements');
        $this->assertNull($doc->querySelector('b'), 'style text must not create elements');
        $this->assertCount(1, $doc->querySelectorAll('script'));
        $this->assertStringContainsString('<\/script>', $out);
        $this->assertStringContainsString('<\/STYLE>', $out);
    }

    public function testLastCallWinsForMetaAndTitleAcrossTemplates(): void
    {
        $page = $this->page('<main var="c"></main>');
        $page->html('c', Template::load('<p>x</p>')->meta('description', 'child')->title('Child'));
        $page->meta('description', 'page-last')->title('Layout');
        $out = $page->render();
        $this->assertStringContainsString('content="page-last"', $out);
        $this->assertStringContainsString('<title>Layout</title>', $out);
    }

    public function testHeadAssetsInCallOrder(): void
    {
        $page = $this->page('<main var="c"></main>');
        $page->cssUrl('/first.css');
        $page->html('c', Template::load('<p>x</p>')->cssUrl('/second.css'));
        $page->cssUrl('/third.css');
        $this->assertMatchesRegularExpression('#/first\.css.*/second\.css.*/third\.css#s', $page->render());
    }

    public function testEachRowsKeepTheirAssets(): void
    {
        $page = $this->page('<ul><li var="r[]">x</li></ul>');
        $page->each('r', [1, 2], fn(Row $r, int $i) => $r->cssUrl("/r$i.css"));
        $out = $page->render();
        $this->assertStringContainsString('/r1.css', $out);
        $this->assertStringContainsString('/r2.css', $out);
    }

    public function testUrlsAlreadyInThePageAreNotAddedAgain(): void
    {
        $page = Template::load('<!doctype html><html><head><link rel="stylesheet" href="/site.css"></head><body><main var="c"></main><script src="/app.js"></script></body></html>');
        $page->html('c', Template::load('<p>x</p>')->cssUrl('/site.css')->jsUrl('/app.js')->cssUrl('/new.css'));
        $out = $page->render();
        $this->assertSame(1, substr_count($out, 'href="/site.css"'));
        $this->assertSame(1, substr_count($out, 'src="/app.js"'));
        $this->assertStringContainsString('href="/new.css"', $out);
    }
}
