<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

class RepeatTargetTest extends TestCase
{
    public function testNestedRepeatsTableRowsAndCells(): void
    {
        $t = Template::load('<table><tbody><tr var="tr[]"><td var="td[]"><span var="v"></span></td></tr></tbody></table>');
        foreach ([[1, 2], [3]] as $cells) {
            $tr = $t->repeat('tr');
            foreach ($cells as $c) {
                $td = $tr->repeat('td');
                $td->text('v', (string)$c);
                $td->insert();
            }
            $tr->insert();
        }
        $this->assertSame('<table><tbody><tr><td><span>1</span></td><td><span>2</span></td></tr><tr><td><span>3</span></td></tr></tbody></table>', $t->render());
    }

    public function testOptgroupOptionIntoSelectElement(): void
    {
        // tk-form's select renderer: options and optgroups go into the `element` var
        $t = Template::load('<div><select var="element"></select><option var="option[]"></option><optgroup var="optgroup[]"><option var="option[]"></option></optgroup></div>');
        $o = $t->repeat('option');
        $o->text('option', 'A')->attr('option', 'value', 'a');
        $o->insertInto('element');
        $g = $t->repeat('optgroup');
        $g->attr('optgroup', 'label', 'G');
        $go = $g->repeat('option');
        $go->text('option', 'B');
        $go->insert();
        $g->insertInto('element');
        $this->assertSame('<div><select><option value="a">A</option><optgroup label="G"><option>B</option></optgroup></select></div>', $t->render());
    }

    public function testMixedRowKindsInOneList(): void
    {
        // tk-base navigation: several kinds of rows in one list
        $t = Template::load('<ul var="nav"><li var="item[]"><a var="link"></a></li><li var="divider[]" class="d"></li></ul>');
        $i = $t->repeat('item');
        $i->text('link', 'One');
        $i->insertInto('nav');
        $d = $t->repeat('divider');
        $d->insertInto('nav');
        $i = $t->repeat('item');
        $i->text('link', 'Two');
        $i->insertInto('nav');
        $this->assertSame('<ul><li><a>One</a></li><li class="d"></li><li><a>Two</a></li></ul>', $t->render());
    }

    public function testPrependToAndEveryTargetNode(): void
    {
        $t = Template::load('<div><ul var="l"><li>end</li></ul><ul var="l"><li>end</li></ul><li var="r[]">r</li></div>');
        $t->repeat('r')->insertFirstInto('l');
        $this->assertSame('<div><ul><li>r</li><li>end</li></ul><ul><li>r</li><li>end</li></ul></div>', $t->render());
    }

    public function testNestedRowTargetsEnclosingRowFirst(): void
    {
        $t = Template::load('<div><ul var="menu"></ul><section var="card[]"><ul var="menu"></ul><li var="link[]"></li></section></div>');
        $card = $t->repeat('card');
        $card->repeat('link')->insertInto('menu');
        $card->insert();
        $this->assertSame('<div><ul></ul><section><ul><li></li></ul></section></div>', $t->render());
    }

    public function testUnknownTargetFails(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('No var `nowhere` in (string) for insertInto()');
        Template::load('<ul><li var="r[]">x</li></ul>')->repeat('r')->insertInto('nowhere');
    }

    public function testLenientUnknownTargetInsertsNothing(): void
    {
        $t = (new Engine(strict: false, logger: new SpyLogger()))->load('<ul><li var="r[]">x</li></ul>');
        $t->repeat('r')->insertInto('nowhere');
        $this->assertSame('<ul></ul>', $t->render());
    }

    public function testEach(): void
    {
        $t = Template::load('<ul><li var="u[]" var-x=""><b var="n"></b></li></ul>');
        $t->each('u', ['a' => 'Ann', 'b' => 'Bob'], fn(Row $r, string $name, string $key) => $r->text('n', "$key:$name"));
        $this->assertSame('<ul><li var-x=""><b>a:Ann</b></li><li var-x=""><b>b:Bob</b></li></ul>', $t->render());
    }

    public function testEachRespectsRowsAddedByTheCallback(): void
    {
        $t = Template::load('<div><ul var="a"></ul><ul var="b"></ul><li var="u[]" var-x=""></li></div>');
        $t->each('u', [1, 2], fn(Row $r, int $n) => $n === 1 ? $r->insertInto('b') : null);
        $this->assertSame('<div><ul></ul><ul><li var-x=""></li></ul><li var-x=""></li></div>', $t->render());
    }

    public function testEachAcceptsGenerators(): void
    {
        $t = Template::load('<ul><li var="u[]"></li></ul>');
        $gen = (function () { yield 'x'; yield 'y'; })();
        $t->each('u', $gen, fn(Row $r, string $v) => $r->text('u', $v));
        $this->assertSame('<ul><li>x</li><li>y</li></ul>', $t->render());
    }
}
