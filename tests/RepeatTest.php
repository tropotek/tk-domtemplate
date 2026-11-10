<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Html;
use Tk\DomTemplate\Kind;
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Scope;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

class RepeatTest extends TestCase
{
    public function testRowsInOrderBeforeFollowingContent(): void
    {
        $t = Template::load('<ul><li var="row[]"><b var="n">x</b></li><li>last</li></ul>');
        foreach (['a', 'b'] as $n) {
            $r = $t->repeat('row');
            $r->text('n', $n);
            $r->insert();
        }
        $this->assertSame('<ul><li><b>a</b></li><li><b>b</b></li><li>last</li></ul>', $t->render());
    }

    public function testPrependGoesBeforeEarlierRows(): void
    {
        $t = Template::load('<ul><li var="row[]" var-x=""><b var="n"></b></li></ul>');
        $r = $t->repeat('row');
        $r->text('n', '2');
        $r->insert();
        $r = $t->repeat('row');
        $r->text('n', '1');
        $r->insertBefore();
        $r = $t->repeat('row');
        $r->text('n', '0');
        $r->insertBefore();
        $this->assertSame('<ul><li var-x=""><b>0</b></li><li var-x=""><b>1</b></li><li var-x=""><b>2</b></li></ul>', $t->render());
    }

    public function testUnusedRepeatRendersNothing(): void
    {
        $this->assertSame('<ul></ul>', Template::load('<ul><li var="row[]">x</li></ul>')->render());
    }

    public function testRowScopeIsIsolated(): void
    {
        $t = Template::load('<div><h1 var="name">page</h1><p var="row[]"><span var="name"></span><i var="flag?">!</i></p></div>');
        $r = $t->repeat('row');
        $r->text('name', 'r1')->show('flag');
        $r->insert();
        $r = $t->repeat('row');
        $r->text('name', 'r2');
        $r->insert();
        $this->assertSame('<div><h1>page</h1><p><span>r1</span><i>!</i></p><p><span>r2</span></p></div>', $t->render());
    }

    public function testRowCanChangeItsOwnElement(): void
    {
        $t = Template::load('<select><option var="opt[]"></option></select>');
        $r = $t->repeat('opt');
        $r->text('opt', 'A')->attr('opt', 'value', 'a');
        $r->insert();
        $this->assertSame('<select><option value="a">A</option></select>', $t->render());
    }

    public function testHiddenRowRootIsNotAdded(): void
    {
        $t = Template::load('<ul><li var="row[]">x</li></ul>');
        $r = $t->repeat('row');
        $r->hide('row');
        $r->insert();
        $this->assertSame('<ul></ul>', $t->render());
    }

    public function testRowCannotBeChangedAfterAdding(): void
    {
        $r = Template::load('<ul><li var="row[]" var-n="">x</li></ul>')->repeat('row');
        $r->insert();
        $this->expectException(\LogicException::class);
        $r->insert();
    }

    public function testRowChangesAfterAddingThrow(): void
    {
        $r = Template::load('<ul><li var="row[]"><b var="n"></b></li></ul>')->repeat('row');
        $r->insert();
        $this->expectException(\LogicException::class);
        $r->text('n', 'late');
    }

    public function testRowsCannotBeAddedAfterParentRenders(): void
    {
        $t = Template::load('<ul><li var="row[]">x</li></ul>');
        $r = $t->repeat('row');
        $t->render();
        $this->expectException(\LogicException::class);
        $r->insert();
    }

    public function testWrongKind(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`title` is not a repeat in (string), use var()');
        Template::load('<h1 var="title">x</h1>')->repeat('title');
    }

    public function testUnknownRepeat(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('No repeat `rows` in (string). Did you mean `row`?');
        Template::load('<ul><li var="row[]">x</li></ul>')->repeat('rows');
    }

    public function testLenientUnknownRepeatIsNeverInserted(): void
    {
        $t = (new Engine(strict: false, logger: new SpyLogger()))->load('<ul><li var="row[]">x</li></ul>');
        $r = $t->repeat('nope');
        $r->insert();
        $this->assertSame('<ul></ul>', $t->render());
    }

    public function testRowsAreNotStandaloneTemplates(): void
    {
        $this->expectException(\LogicException::class);
        Row::load('<p>x</p>');
    }

    public function testRowsDoNotRenderAlone(): void
    {
        $r = Template::load('<ul><li var="row[]">x</li></ul>')->repeat('row');
        $this->expectException(\LogicException::class);
        $r->render();
    }

    public function testWhitespaceAroundRepeatNameStillScopes(): void
    {
        $root = Html::parseFragment(\Dom\HTMLDocument::createEmpty(), '<div><span var="name">p</span><ul><li var=" users[] "><b var="name?">r</b></li></ul></div>');
        $s = Scope::build($root, 't', fn(string $m) => throw new TemplateException($m));
        $this->assertSame(Kind::Var, $s->kind('name'));
        $this->assertCount(1, $s->nodes('name'));
        $this->assertSame(Kind::Repeat, $s->kind('users'));
    }

    public function testThousandRowsUnderOneSecond(): void
    {
        $t = Template::load('<table><tbody><tr var="row[]"><td var="a"></td><td var="b"></td><td var="c"></td></tr></tbody></table>');
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $r = $t->repeat('row');
            $r->text('a', "a$i")->text('b', "b$i")->text('c', "c$i");
            $r->insert();
        }
        $out = $t->render();
        $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
        $this->assertSame(1000, substr_count($out, '<tr>'));
    }
}
