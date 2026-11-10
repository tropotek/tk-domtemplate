<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class HandleTest extends TestCase
{
    public function testTextEscapesAndUpdatesEveryNode(): void
    {
        $t = Template::load('<div><p var="m">a</p><span var="m">b</span></div>');
        $t->text('m', 'Hi <b> & "q"');
        $this->assertSame('<div><p>Hi &lt;b&gt; &amp; "q"</p><span>Hi &lt;b&gt; &amp; "q"</span></div>', $t->render());
    }

    public function testHtmlAppendPrependReplace(): void
    {
        $t = Template::load('<div><p var="a">x</p><p var="b">x</p><p var="c">x</p><p var="d">x</p></div>');
        $t->html('a', '<b>A</b>')->append('b', '<i>1</i>')->prepend('c', '<i>0</i>')->replace('d', '<hr>');
        $this->assertSame('<div><p><b>A</b></p><p>x<i>1</i></p><p><i>0</i>x</p><hr></div>', $t->render());
    }

    public function testHtmlIntoTableBodyKeepsRows(): void
    {
        $t = Template::load('<table><tbody var="rows"></tbody></table>');
        $t->html('rows', '<tr><td>1</td></tr>');
        $this->assertSame('<table><tbody><tr><td>1</td></tr></tbody></table>', $t->render());
    }

    public function testAttributes(): void
    {
        $t = Template::load('<a var="l" class="x" title="old">l</a>');
        $t->var('l')->attr('href', '/p?a=1&b="2"')->attr(['data-n' => 5, 'download' => true, 'title' => null]);
        $this->assertSame('<a class="x" href="/p?a=1&amp;b=&quot;2&quot;" data-n="5" download="">l</a>', $t->render());
    }

    public function testAttrShortcutAndRemoveAttr(): void
    {
        $t = Template::load('<a var="l" title="t" rel="x">l</a>');
        $t->attr('l', 'href', '/a')->attr('l', ['data-off' => false]);
        $t->var('l')->removeAttr('rel');
        $this->assertSame('<a title="t" href="/a">l</a>', $t->render());
    }

    public function testClassesAreWholeTokens(): void
    {
        $t = Template::load('<b var="b" class="btn btn-primary a">x</b>');
        $t->var('b')->removeClass('btn')->addClass('c d')->addClass(['e']);
        $this->assertSame('<b class="btn-primary a c d e">x</b>', $t->render());
    }

    public function testClassShortcuts(): void
    {
        $t = Template::load('<b var="b" class="a b">x</b>');
        $t->addClass('b', 'c')->removeClass('b', ['a', 'b']);
        $this->assertSame('<b class="c">x</b>', $t->render());
    }

    public function testRemovingTheLastClassDropsTheAttribute(): void
    {
        $t = Template::load('<b var="b" class="a">x</b>');
        $t->removeClass('b', 'a');
        $this->assertSame('<b>x</b>', $t->render());
    }

    public function testGettersAndHas(): void
    {
        $t = Template::load('<p var="p" title="t">hello</p>');
        $this->assertSame('hello', $t->var('p')->getText());
        $this->assertSame('t', $t->var('p')->getAttr('title'));
        $this->assertNull($t->var('p')->getAttr('nope'));
        $this->assertSame(1, $t->var('p')->count());
        $this->assertTrue($t->has('p'));
        $this->assertFalse($t->has('q'));
    }

    public function testEncodedValuesSurvive(): void
    {
        $t = Template::load('<p var="p">x</p>');
        $t->var('p')->text('é 日本 😀')->attr('title', 'a "q" & <b>');
        $this->assertSame('<p title="a &quot;q&quot; &amp; <b>">é 日本 😀</p>', $t->render());
    }
}
