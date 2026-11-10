<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Place;
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

class AssetTest extends TestCase
{
    private function page(): Template
    {
        return Template::load("<!doctype html>\n<html><head><title>Old</title></head><body><main var=\"c\"></main><script src=\"/lib.js\"></script></body></html>");
    }

    public function testInlineIsWrittenAtTheEndOfItsTemplate(): void
    {
        $page = $this->page();
        $child = Template::load('<section>s</section>')->js('init();')->css('.a{}');
        $page->html('c', $child);
        $this->assertStringContainsString('<main><section>s</section><script>init();</script><style>.a{}</style></main>', $page->render());
    }

    public function testHeadAndEndPlacement(): void
    {
        $page = $this->page();
        $page->html('c', Template::load('<p>x</p>')->js('h();', Place::Head)->js('e();', Place::End)->css('.h{}', Place::Head));
        $out = $page->render();
        $this->assertMatchesRegularExpression('#<head>.*<script>h\(\);</script>.*<style>\.h\{\}</style>.*</head>#s', $out);
        $this->assertMatchesRegularExpression('#<script src="/lib.js"></script><script>e\(\);</script></body>#', $out);
    }

    public function testUrlsGoToHeadOncePerPage(): void
    {
        $page = $this->page();
        $a = Template::load('<p>a</p>')->cssUrl('/w.css')->jsUrl('/w.js', ['defer' => true]);
        $b = Template::load('<p>b</p>')->cssUrl('/w.css')->jsUrl('/w.js', ['defer' => true]);
        $page->append('c', $a)->append('c', $b);
        $out = $page->render();
        $this->assertSame(1, substr_count($out, '<link rel="stylesheet" href="/w.css">'));
        $this->assertSame(1, substr_count($out, '<script src="/w.js" defer=""></script>'));
        $this->assertMatchesRegularExpression('#<head>.*/w\.css.*/w\.js.*</head>#s', $out);
    }

    public function testKeyedCodeOncePerPageIncludingRows(): void
    {
        $page = Template::load('<ul><li var="r[]">x</li></ul>');
        foreach ([1, 2, 3] as $i) {
            $r = $page->repeat('r');
            $r->js('setup();', key: 'setup');
            $r->append();
        }
        $this->assertSame(1, substr_count($page->render(), 'setup();'));
    }

    public function testRowInlineAssetGoesAtTheEndOfTheRow(): void
    {
        $page = Template::load('<ul><li var="r[]"><b>x</b></li></ul>');
        $page->each('r', [1, 2], fn(Row $r, int $i) => $r->js("row$i();"));
        $this->assertSame('<ul><li><b>x</b><script>row1();</script></li><li><b>x</b><script>row2();</script></li></ul>', $page->render());
    }

    public function testRowAddedToSeveralTargetsCarriesItsInlineAssetToEach(): void
    {
        $page = Template::load('<div><ul var="l"></ul><ul var="l"></ul><li var="r[]">x</li></div>');
        $page->repeat('r')->js('a();')->js('k();', key: 'k')->appendTo('l');
        $out = $page->render();
        $this->assertSame(2, substr_count($out, 'a();'));
        $this->assertSame(1, substr_count($out, 'k();'));
    }

    public function testRowHeadAssetsBubbleToThePage(): void
    {
        $page = $this->page();
        $page->html('c', '<ul><li var="r[]">x</li></ul>');
        $list = Template::load('<ul><li var="r[]">x</li></ul>');
        $list->repeat('r')->cssUrl('/row.css')->append();
        $page->html('c', $list);
        $this->assertMatchesRegularExpression('#<head>.*/row\.css.*</head>#s', $page->render());
    }

    public function testTitleAndMeta(): void
    {
        $page = $this->page()->title('New')->meta('description', 'D');
        $page->html('c', Template::load('<p>x</p>')->meta('description', 'From child'));
        $out = $page->render();
        $this->assertStringContainsString('<title>New</title>', $out);
        $this->assertSame(1, substr_count($out, 'name="description"'));
        $this->assertStringContainsString('content="From child"', $out);
    }

    public function testTitleCreatedWhenMissing(): void
    {
        $page = Template::load('<!doctype html><html><head></head><body></body></html>')->title('T & <x>');
        $this->assertStringContainsString('<head><title>T &amp; &lt;x&gt;</title></head>', $page->render());
    }

    public function testFragmentFallsBackToInline(): void
    {
        $frag = Template::load('<div>x</div>')->js('h();', Place::Head)->cssUrl('/f.css')->title('dropped');
        $this->assertSame('<div>x</div><script>h();</script><link rel="stylesheet" href="/f.css">', $frag->render());
    }

    public function testAssetsAppliedOnceWhenRenderedTwice(): void
    {
        $t = Template::load('<div>x</div>')->js('a();');
        $this->assertSame($t->render(), $t->render());
        $this->assertSame(1, substr_count($t->render(), 'a();'));
    }

    public function testHiddenComponentContributesNoAssets(): void
    {
        $page = $this->page();
        $page->html('c', '<div var="admin?"></div>');
        $page2 = Template::load("<!doctype html>\n<html><head></head><body><div var=\"admin?\"></div></body></html>");
        $page2->html('admin', Template::load('<p>x</p>')->cssUrl('/admin.css')->js('adm();', Place::End));
        $out = $page2->render();
        $this->assertStringNotContainsString('admin.css', $out);
        $this->assertStringNotContainsString('adm();', $out);
    }

    public function testChildSharedByTwoPagesHandsUpAssetsToBoth(): void
    {
        $child = Template::load('<p>c</p>')->cssUrl('/c.css');
        $a = $this->page();
        $a->html('c', $child);
        $b = $this->page();
        $b->html('c', $child);
        $this->assertStringContainsString('/c.css', $a->render());
        $this->assertStringContainsString('/c.css', $b->render());
    }

    public function testScriptAttributesAndEscaping(): void
    {
        $t = Template::load('<!doctype html><html><head></head><body></body></html>');
        $t->jsUrl('/m.js?a=1&b=2', ['type' => 'module', 'async' => true, 'nomodule' => false]);
        $t->js('if (a < b && c) {}', Place::End);
        $out = $t->render();
        $this->assertStringContainsString('<script src="/m.js?a=1&amp;b=2" type="module" async=""></script>', $out);
        $this->assertStringContainsString('<script>if (a < b && c) {}</script></body>', $out);
    }

    public function testAssetsAfterRenderThrow(): void
    {
        $t = Template::load('<p>x</p>');
        $t->render();
        $this->expectException(\LogicException::class);
        $t->js('late();');
    }
}
