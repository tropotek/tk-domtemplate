<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class CloneTest extends TestCase
{
    public function testCloneIsIndependent(): void
    {
        $a = Template::load('<p var="x?">a</p>');
        $a->text('x', 'one');
        $b = clone $a;
        $b->text('x', 'two')->show('x');
        $this->assertSame('', $a->render());
        $this->assertSame('<p>two</p>', $b->render());
    }

    public function testCloningRenderedThrows(): void
    {
        $a = Template::load('<p>a</p>');
        $a->render();
        $this->expectException(\LogicException::class);
        $b = clone $a;
    }

    public function testCloneKeepsVisibilityAndReplacedNames(): void
    {
        $a = Template::load('<div><p var="opt?">o</p><p var="r">r</p></div>');
        $a->show('opt')->replace('r', '<b>new</b>');
        $b = clone $a;
        $b->addClass('r', 'c');
        $this->assertSame('<div><p>o</p><b class="c">new</b></div>', $b->render());
        $this->assertSame('<div><p>o</p><b>new</b></div>', $a->render());
    }

    public function testCloneKeepsPendingInserts(): void
    {
        $child = Template::load('<i>c</i>');
        $a = Template::load('<div var="d"></div>');
        $a->html('d', $child);
        $b = clone $a;
        $this->assertSame('<div><i>c</i></div>', $b->render());
        $this->assertSame('<div><i>c</i></div>', $a->render());
    }

    public function testCloneKeepsRowsAndPrependPosition(): void
    {
        $a = Template::load('<ul><li var="r[]"><b var="n"></b></li></ul>');
        $row = $a->repeat('r');
        $row->text('n', '2');
        $row->append();
        $b = clone $a;
        $first = $b->repeat('r');
        $first->text('n', '1');
        $first->prepend();
        $this->assertSame('<ul><li><b>1</b></li><li><b>2</b></li></ul>', $b->render());
        $this->assertSame('<ul><li><b>2</b></li></ul>', $a->render());
    }

    public function testCloneOfFullPageWithTemplateContent(): void
    {
        $a = Template::load("<!DOCTYPE html><html><head></head><body><p var=\"p\">x</p><template x-if=\"o\"><b>t</b></template></body></html>");
        $b = clone $a;
        $b->text('p', 'y');
        $this->assertStringContainsString('<p>y</p><template x-if="o"><b>t</b></template>', $b->render());
        $this->assertStringContainsString('<p>x</p>', $a->render());
    }

    public function testRowsCannotBeCloned(): void
    {
        $row = Template::load('<ul><li var="r[]">x</li></ul>')->repeat('r');
        $this->expectException(\LogicException::class);
        $c = clone $row;
    }
}
