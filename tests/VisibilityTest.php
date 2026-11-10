<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class VisibilityTest extends TestCase
{
    public function testOptionalRemovedUnlessShown(): void
    {
        $t = Template::load('<div><p var="a?">A</p><p var="b?">B</p><p var="c?">C</p></div>');
        $t->show('a')->show('b', false)->var('c')->text('changed')->show();
        $this->assertSame('<div><p>A</p><p>changed</p></div>', $t->render());
    }

    public function testPlainVarCanBeHidden(): void
    {
        $t = Template::load('<div><p var="a">A</p><p var="b">B</p></div>');
        $t->hide('a')->show('b', true);
        $this->assertSame('<div><p>B</p></div>', $t->render());
    }

    public function testLastCallWins(): void
    {
        $t = Template::load('<p var="a?">A</p>');
        $t->show('a')->hide('a')->show('a');
        $this->assertSame('<p>A</p>', $t->render());
    }

    public function testHandleHide(): void
    {
        $t = Template::load('<div><p var="a">A</p><i var="a">A2</i><b>k</b></div>');
        $t->var('a')->text('x')->hide();
        $this->assertSame('<div><b>k</b></div>', $t->render());
    }

    public function testHtmlHiddenAttributeIsLeftAlone(): void
    {
        $html = '<div><p var="a" hidden>A</p><p hidden>B</p></div>';
        $this->assertSame('<div><p hidden="">A</p><p hidden="">B</p></div>', Template::load($html)->render());
    }
}
