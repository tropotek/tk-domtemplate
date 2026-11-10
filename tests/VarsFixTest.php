<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes from the phase 2 review
 */
class VarsFixTest extends TestCase
{
    public function testOverwritingAContainerWithAnOptionalInsideRenders(): void
    {
        $t = Template::load('<div var="body"><p var="empty?">Nothing</p></div>');
        $t->html('body', '<p>x</p>');
        $this->assertSame('<div><p>x</p></div>', $t->render());
    }

    public function testOverwritingAContainerWithARepeatSampleRenders(): void
    {
        $t = Template::load('<ul var="w"><li var="r[]">x</li></ul>');
        $t->text('w', 'none');
        $this->assertSame('<ul>none</ul>', $t->render());
    }

    public function testReplacedOptionalStaysHiddenUnlessShown(): void
    {
        $t = Template::load('<div><p var="admin?">x</p><b>k</b></div>');
        $t->replace('admin', '<section class="panel">admin</section>');
        $this->assertSame('<div><b>k</b></div>', $t->render());
    }

    public function testReplacedOptionalCanBeShown(): void
    {
        $t = Template::load('<div><p var="admin?">x</p></div>');
        $t->replace('admin', '<section>admin</section>')->show('admin');
        $this->assertSame('<div><section>admin</section></div>', $t->render());
    }

    public function testHideAfterReplaceRemovesTheReplacement(): void
    {
        $t = Template::load('<div><p var="a">x</p><b>k</b></div>');
        $t->replace('a', '<i>1</i><i>2</i>')->hide('a');
        $this->assertSame('<div><b>k</b></div>', $t->render());
    }

    public function testHandleKeepsWorkingOnTheReplacement(): void
    {
        $t = Template::load('<div><p var="a">x</p></div>');
        $t->var('a')->replaceWith('<span>n</span>')->addClass('c');
        $this->assertSame('<div><span class="c">n</span></div>', $t->render());
    }

    public function testReplaceTableRowInFragment(): void
    {
        $t = Template::load('<table><tbody><tr var="r"><td>a</td></tr></tbody></table>');
        $t->replace('r', '<tr><td>b</td></tr>');
        $this->assertSame('<table><tbody><tr><td>b</td></tr></tbody></table>', $t->render());
    }

    public function testAlpineShorthandAttributes(): void
    {
        $t = Template::load('<button var="b" @click="old()">b</button>');
        $t->attr('b', ['@click' => 'open = !open', '@keydown.escape.window' => 'open = false', ':class' => '{a: open}', 'x-on:focus' => 'f()']);
        $this->assertSame('<button @click="open = !open" @keydown.escape.window="open = false" :class="{a: open}" x-on:focus="f()">b</button>', $t->render());
    }

    public function testAlpineAttributeRemoval(): void
    {
        $t = Template::load('<button var="b" @click="x()">b</button>');
        $t->var('b')->removeAttr('@click');
        $this->assertSame('<button>b</button>', $t->render());
    }

    public function testInvalidAttributeNameThrowsTemplateException(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Invalid attribute name');
        (new Engine(strict: false))->load('<p var="p">x</p>')->attr('p', 'a b', 'x');
    }

    public function testListKeyAttributeThrowsTemplateException(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<p var="p">x</p>')->attr('p', ['disabled']); // @phpstan-ignore argument.type
    }

    public function testHtmlIntoSelectAndRow(): void
    {
        $t = Template::load('<div><select var="s"></select><table><tbody><tr var="tr"></tr></tbody></table></div>');
        $t->html('s', '<option>a</option><optgroup label="g"><option>b</option></optgroup>')->append('tr', '<td>1</td><th>2</th>');
        $this->assertSame('<div><select><option>a</option><optgroup label="g"><option>b</option></optgroup></select><table><tbody><tr><td>1</td><th>2</th></tr></tbody></table></div>', $t->render());
    }

    public function testAlpineSurvivesHtmlAndAppend(): void
    {
        $t = Template::load('<div var="d"></div>');
        $t->html('d', '<button @click="a()" :class="{b:c}" x-on:keyup="k()" hx-get="/x">1</button>')->append('d', '<template x-for="i in l"><b x-text="i"></b></template>');
        $this->assertSame('<div><button @click="a()" :class="{b:c}" x-on:keyup="k()" hx-get="/x">1</button><template x-for="i in l"><b x-text="i"></b></template></div>', $t->render());
    }
}
