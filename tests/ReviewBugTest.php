<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Bugs from the pre-release review
 */
class ReviewBugTest extends TestCase
{
    // Engine inheritance

    public function testGrandchildUsesThePageEngine(): void
    {
        $child = Template::load('<div var="slot"></div>');
        $grandchild = Template::load('<p var="g">g</p>');
        $child->html('slot', $grandchild);
        $page = (new Engine(strict: false, logger: new SpyLogger()))->load('<main var="m"></main>');
        $page->html('m', $child);
        $this->assertFalse($grandchild->engine()->strict);
        $grandchild->text('nope', 'x');
        $this->assertSame('<main><div><p>g</p></div></main>', $page->render());
    }

    public function testMutualInsertDoesNotLoopOnEngineLookup(): void
    {
        $a = Template::load('<div var="x"></div>');
        $b = Template::load('<div var="y"></div>');
        $a->html('x', $b);
        $b->html('y', $a);
        $this->assertTrue($a->engine()->strict);
        $this->assertTrue($b->engine()->strict);
    }

    // Nested rows added to an outer var

    public function testNestedRowAddedOutwardKeepsInsertsAndAssets(): void
    {
        $t = Template::load('<div var="out"></div><ul><li var="row[]"><i var="sub[]"><b var="s"></b></i></li></ul>');
        $row = $t->repeat('row');
        $sub = $row->repeat('sub');
        $sub->html('s', Template::load('<em>child</em>'));
        $sub->js('sub();');
        $sub->insertInto('out');
        $this->assertSame('<div><i><b><em>child</em></b><script>sub();</script></i></div><ul></ul>', $t->render());
    }

    // Asset keys

    public function testKeysAreSeparatePerAssetType(): void
    {
        $out = Template::load('<p>x</p>')->css('.a{}', key: 'widget')->js('w();', key: 'widget')->render();
        $this->assertSame('<p>x</p><style>.a{}</style><script>w();</script>', $out);
    }

    // Meta names

    public function testMetaNameMatchIgnoresCase(): void
    {
        $t = Template::load('<!doctype html><html><head><meta name="Description" content="old"></head><body></body></html>');
        $t->meta('description', 'new');
        $out = $t->render();
        $this->assertSame(1, substr_count($out, '<meta '));
        $this->assertStringContainsString('content="new"', $out);
    }

    // <template> content check

    public function testVarTextInsideTemplateAttributeIsNotAMarker(): void
    {
        $t = Template::load('<template x-if="a"><div title="my var = 3" x-data="{ var = 1 }">x</div></template>');
        $this->assertSame('<template x-if="a"><div title="my var = 3" x-data="{ var = 1 }">x</div></template>', $t->render());
    }

    public function testVarMarkerInsideTemplateIsStillReported(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('var markers inside <template> are not supported');
        Template::load('<template><table><tr var="r"><td>x</td></tr></table></template>');
    }

    public function testVarMarkerInNestedTemplateIsReported(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<template><template><p var="x">x</p></template></template>');
    }

    // Placeholders, <template> targets and same-name nesting

    public function testContentChangeAfterReplacingWithATemplateFails(): void
    {
        $t = Template::load('<p var="a">x</p>');
        $h = $t->var('a')->replace(Template::load('<b>t</b>'));
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`a` in (string) was replaced by a template; change that template instead');
        $h->text('lost');
    }

    public function testAttrAfterReplacingWithATemplateStillCarriesOver(): void
    {
        $t = Template::load('<p var="a">x</p>');
        $t->var('a')->replace(Template::load('<b>t</b>'))->addClass('c');
        $this->assertSame('<b class="c">t</b>', $t->render());
    }

    public function testTemplateInsertIntoTemplateElementFails(): void
    {
        $t = Template::load('<template var="t"></template>');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage("Can't insert a template into <template> `t` in (string); insert its render() string instead");
        $t->html('t', Template::load('<p>x</p>'));
    }

    public function testStringIntoTemplateElementStillWorks(): void
    {
        $t = Template::load('<template var="t"></template>');
        $t->html('t', '<p>x</p>');
        $this->assertSame('<template><p>x</p></template>', $t->render());
    }

    public function testReplacingSameNameNestedNodesKeepsTheNameUsable(): void
    {
        $t = Template::load('<div var="x"><span var="x">in</span></div>');
        $t->replace('x', '<p>new</p>');
        $t->addClass('x', 'c');
        $this->assertSame('<p class="c">new</p>', $t->render());
    }

    public function testHtmlOnSameNameNestedNodesKeepsTheNameUsable(): void
    {
        $t = Template::load('<div var="x"><span var="x">in</span></div>');
        $t->html('x', 'new');
        $t->addClass('x', 'c');
        $this->assertSame('<div class="c">new</div>', $t->render());
    }
}
