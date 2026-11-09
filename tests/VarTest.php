<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class VarTest extends TestCase
{
    private function tpl(): Template
    {
        return Template::load('<div><p var="msg" class="a b">Default</p><span var="msg">Default</span></div>');
    }

    public function testSetTextEscapesAndUpdatesEveryNodeWithTheName(): void
    {
        $t = $this->tpl();
        $t->setText('msg', 'Hi <b> & bye');
        $html = $t->toString();

        $this->assertSame(2, substr_count($html, 'Hi &lt;b&gt; &amp; bye'));
        $this->assertStringNotContainsString('var=', $html);
    }

    public function testSetHtmlInsertsNodes(): void
    {
        $t = $this->tpl();
        $t->setHtml('msg', '<strong>Bold</strong> text');
        $xp = $this->render($t);

        $this->assertSame(['Bold', 'Bold'], $this->texts($xp, '//strong'));
    }

    public function testAppendAndPrependKeepExistingContent(): void
    {
        $t = $this->tpl();
        $t->appendText('msg', ' after');
        $t->prependText('msg', 'before ');

        $this->assertSame(['before Default after'], $this->texts($this->render($t), '//p'));
    }

    public function testGetTextReturnsCurrentContent(): void
    {
        $t = $this->tpl();
        $this->assertSame('Default', $t->getText('msg'));
    }

    public function testAttributes(): void
    {
        $t = $this->tpl();
        $t->setAttr('msg', 'title', 'One');
        $t->setAttr('msg', ['data-x' => '1', 'data-y' => '2']);
        $t->removeAttr('msg', 'class');
        $xp = $this->render($t);

        $p = $xp->query('//p')->item(0);
        $this->assertSame('One', $p->getAttribute('title'));
        $this->assertSame('1', $p->getAttribute('data-x'));
        $this->assertSame('2', $p->getAttribute('data-y'));
        $this->assertFalse($p->hasAttribute('class'));
    }

    public function testCssClasses(): void
    {
        $t = $this->tpl();
        $t->addCss('msg', 'c');
        $t->addCss('msg', ['d', 'e']);
        $t->removeCss('msg', 'a');
        $classes = explode(' ', $this->render($t)->query('//p')->item(0)->getAttribute('class'));

        sort($classes);
        $this->assertSame(['b', 'c', 'd', 'e'], $classes);
    }

    public function testVarExists(): void
    {
        $t = $this->tpl();
        $this->assertTrue($t->varExists('msg'));
        $this->assertFalse($t->varExists('nope'));
    }

    public function testChangesAfterRenderAreIgnored(): void
    {
        $t = $this->tpl();
        $first = $t->toString();
        $t->setText('msg', 'Too late');

        $this->assertTrue($t->isParsed());
        $this->assertSame($first, $t->toString());
    }
}
