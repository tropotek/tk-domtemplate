<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class ChoiceRepeatTest extends TestCase
{
    public function testChoicesAreRemovedUnlessVisible(): void
    {
        $t = Template::load('<div><p choice="shown">A</p><p choice="hidden">B</p><p choice="toggled">C</p></div>');
        $t->setVisible('shown');
        $t->setVisible('toggled');
        $t->setVisible('toggled', false);

        $this->assertSame(['A'], $this->texts($this->render($t), '//p'));
    }

    public function testRepeatAddsOneRowPerItemInOrder(): void
    {
        $t = Template::load('<ul><li repeat="row" var="name">x</li><li>last</li></ul>');
        foreach (['a', 'b', 'c'] as $name) {
            $row = $t->getRepeat('row');
            $row->setText('name', $name);
            $row->appendRepeat();
        }

        $this->assertSame(['a', 'b', 'c', 'last'], $this->texts($this->render($t), '//li'));
    }

    public function testUnusedRepeatIsRemoved(): void
    {
        $t = Template::load('<ul><li repeat="row">x</li></ul>');
        $this->assertSame([], $this->texts($this->render($t), '//li'));
    }

    public function testChoiceInsideRepeatAppliesToThatRowOnly(): void
    {
        $t = Template::load('<table><tr repeat="row"><td var="n"></td><td><b choice="on">on</b></td></tr></table>');
        foreach ([1 => true, 2 => false, 3 => true] as $n => $on) {
            $row = $t->getRepeat('row');
            $row->setText('n', (string)$n);
            $row->setVisible('on', $on);
            $row->appendRepeat();
        }
        $xp = $this->render($t);

        $this->assertCount(3, $xp->query('//tr'));
        $this->assertCount(2, $xp->query('//b'));
        $this->assertCount(0, $xp->query('//tr[2]//b'));
    }

    public function testRepeatExistsAndInvalidName(): void
    {
        $t = Template::load('<ul><li repeat="row">x</li></ul>');
        $this->assertTrue($t->repeatExists('row'));
        $this->assertFalse($t->repeatExists('nope'));

        $this->expectException(\Exception::class);
        $t->getRepeat('nope');
    }
}
