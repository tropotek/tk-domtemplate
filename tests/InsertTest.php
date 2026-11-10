<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;

class InsertTest extends TestCase
{
    public function testInsertIsLazy(): void
    {
        $page = Template::load('<main var="content"></main>');
        $child = Template::load('<section><h2 var="t">x</h2></section>');
        $page->html('content', $child);
        $child->text('t', 'changed after insert');
        $this->assertSame('<main><section><h2>changed after insert</h2></section></main>', $page->render());
        $this->assertTrue($child->isRendered());
    }

    public function testAllFourOperationsWithTemplates(): void
    {
        $page = Template::load('<div><p var="a">x</p><p var="b">x</p><p var="c">x</p><p var="d">x</p></div>');
        $mk = fn(string $s) => Template::load("<i>$s</i>");
        $page->html('a', $mk('h'))->append('b', $mk('ap'))->prepend('c', $mk('pre'))->replace('d', $mk('rep'));
        $this->assertSame('<div><p><i>h</i></p><p>x<i>ap</i></p><p><i>pre</i>x</p><i>rep</i></div>', $page->render());
    }

    public function testCallOrderIsKeptWhenMixingStringsAndTemplates(): void
    {
        $page = Template::load('<div var="d"></div>');
        $page->append('d', '<b>1</b>')->append('d', Template::load('<i>2</i>'))->append('d', '<b>3</b>');
        $this->assertSame('<div><b>1</b><i>2</i><b>3</b></div>', $page->render());
    }

    public function testStringHtmlAfterTemplateInsertOverwrites(): void
    {
        $page = Template::load('<div var="d"></div>');
        $page->html('d', Template::load('<i>t</i>'))->html('d', '<b>s</b>');
        $this->assertSame('<div><b>s</b></div>', $page->render());
    }

    public function testSameTemplateInTwoPlacesBuildsOnce(): void
    {
        $page = Template::load('<div><p var="a"></p><p var="b"></p></div>');
        $child = Template::load('<b>c</b>');
        $page->html('a', $child)->html('b', $child);
        $this->assertSame('<div><p><b>c</b></p><p><b>c</b></p></div>', $page->render());
    }

    public function testFullDocumentChildInsertsBodyChildren(): void
    {
        $page = Template::load('<main var="content"></main>');
        $page->html('content', Template::load("<!doctype html>\n<html><head><title>x</title></head><body><p>one</p><p>two</p></body></html>"));
        $this->assertSame('<main><p>one</p><p>two</p></main>', $page->render());
    }

    public function testRowsIntoTableBodyFromAChildTemplate(): void
    {
        $page = Template::load('<table><tbody var="rows"></tbody></table>');
        $page->html('rows', Template::load('<tr><td>1</td></tr><tr><td>2</td></tr>'));
        $this->assertSame('<table><tbody><tr><td>1</td></tr><tr><td>2</td></tr></tbody></table>', $page->render());
    }

    public function testCircularInsertThrows(): void
    {
        $a = Template::load('<div var="slot"></div>');
        $b = Template::load('<div var="slot"></div>');
        $a->html('slot', $b);
        $b->html('slot', $a);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular template insert: (string) → (string) → (string)');
        $a->render();
    }

    public function testSelfInsertThrows(): void
    {
        $a = Template::load('<div var="slot"></div>');
        $a->html('slot', $a);
        $this->expectException(\LogicException::class);
        $a->render();
    }

    public function testInsertedRowContentIsBuilt(): void
    {
        $page = Template::load('<ul><li var="r[]"><span var="cell"></span></li></ul>');
        $row = $page->repeat('r');
        $row->html('cell', Template::load('<em>e</em>'));
        $row->append();
        $this->assertSame('<ul><li><span><em>e</em></span></li></ul>', $page->render());
    }

    public function testTemplateInsertInARowAddedToSeveralTargets(): void
    {
        $page = Template::load('<div><ul var="l"></ul><ul var="l"></ul><li var="r[]"><span var="c"></span></li></div>');
        $row = $page->repeat('r');
        $row->html('c', Template::load('<em>e</em>'));
        $row->appendTo('l');
        $this->assertSame('<div><ul><li><span><em>e</em></span></li></ul><ul><li><span><em>e</em></span></li></ul></div>', $page->render());
    }

    public function testTemplateInsertInsideNestedRows(): void
    {
        $page = Template::load('<table><tr var="tr[]"><td var="td[]"></td></tr></table>');
        $tr = $page->repeat('tr');
        $td = $tr->repeat('td');
        $td->html('td', Template::load('<b>x</b>'));
        $td->append();
        $tr->append();
        $this->assertSame('<table><tbody><tr><td><b>x</b></td></tr></tbody></table>', $page->render());
    }

    public function testReplacedOptionalWithTemplateStaysHiddenUnlessShown(): void
    {
        $page = Template::load('<div><p var="admin?">x</p><b>k</b></div>');
        $page->replace('admin', Template::load('<section>panel</section>'));
        $this->assertSame('<div><b>k</b></div>', $page->render());
        $shown = Template::load('<div><p var="admin?">x</p></div>');
        $shown->replace('admin', Template::load('<section>panel</section>'))->show('admin');
        $this->assertSame('<div><section>panel</section></div>', $shown->render());
    }

    public function testInsertIntoHiddenNodeIsDropped(): void
    {
        $page = Template::load('<div><p var="a?"></p><b>k</b></div>');
        $page->html('a', Template::load('<i>never</i>'));
        $this->assertSame('<div><b>k</b></div>', $page->render());
    }

    public function testAlpineAndTemplateContentSurviveInsert(): void
    {
        $page = Template::load('<main var="c"></main>');
        $page->html('c', Template::load('<button @click="x++" hx-post="/y">b</button><template x-for="i in l"><b x-text="i"></b></template>'));
        $this->assertSame('<main><button @click="x++" hx-post="/y">b</button><template x-for="i in l"><b x-text="i"></b></template></main>', $page->render());
    }

    public function testChildInheritsParentEngine(): void
    {
        $engine = new Engine(strict: false);
        $page = $engine->load('<main var="c"></main>');
        $child = Template::load('<p>x</p>');
        $page->html('c', $child);
        $page->render();
        $this->assertSame($engine, $child->engine());
    }

    public function testChildOwnEngineWins(): void
    {
        $own = new Engine();
        $page = (new Engine(strict: false))->load('<main var="c"></main>');
        $child = $own->load('<p>x</p>');
        $page->html('c', $child);
        $page->render();
        $this->assertSame($own, $child->engine());
    }

    public function testRowAppendWithOneArgumentThrows(): void
    {
        $row = Template::load('<ul><li var="r[]"><b var="n"></b></li></ul>')->repeat('r');
        $this->expectException(\ArgumentCountError::class);
        $row->append('n');
    }

    public function testRowAppendShortcutWithTemplate(): void
    {
        $page = Template::load('<ul><li var="r[]"><b var="n"></b></li></ul>');
        $row = $page->repeat('r');
        $row->append('n', Template::load('<i>t</i>'));
        $row->append();
        $this->assertSame('<ul><li><b><i>t</i></b></li></ul>', $page->render());
    }

    public function testNoPlaceholderLeaksIntoOutput(): void
    {
        $page = Template::load('<div><p var="a"></p><p var="b"></p></div>');
        $page->html('a', Template::load('<i>1</i>'));
        $page->html('a', '');
        $this->assertSame('<div><p></p><p></p></div>', $page->render());
    }

    public function testRenderedChildCanStillBeRead(): void
    {
        $page = Template::load('<main var="c"></main>');
        $child = Template::load('<p var="t">hello</p>');
        $page->html('c', $child);
        $page->render();
        $this->assertSame('hello', $child->var('t')->getText());
        $this->expectException(\LogicException::class);
        $child->text('t', 'late');
    }
}
