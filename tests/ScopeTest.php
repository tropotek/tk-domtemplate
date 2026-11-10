<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Html;
use Tk\DomTemplate\Kind;
use Tk\DomTemplate\Scope;
use Tk\DomTemplate\TemplateException;

class ScopeTest extends TestCase
{
    public function testIndexesKindsAndNodes(): void
    {
        $s = $this->scope('<div><h1 var="title">a</h1><p var="title">b</p><b var="admin?">x</b><ul><li var="users[]">u</li></ul></div>');
        $this->assertSame(Kind::Var, $s->kind('title'));
        $this->assertCount(2, $s->nodes('title'));
        $this->assertSame(Kind::Optional, $s->kind('admin'));
        $this->assertSame(Kind::Repeat, $s->kind('users'));
        $this->assertSame('LI', $s->sample('users')?->tagName);
        $this->assertNull($s->kind('nope'));
        $this->assertSame([], $s->nodes('nope'));
        $this->assertSame(['title', 'admin', 'users'], $s->names());
    }

    public function testVarsInsideRepeatBelongToTheRow(): void
    {
        $s = $this->scope('<div><span var="name">page</span><ul><li var="users[]"><span var="name">row</span><i var="roles[]"></i></li></ul></div>');
        $this->assertCount(1, $s->nodes('name'));
        $this->assertNull($s->kind('roles'));
        $sample = $s->sample('users');
        $this->assertNotNull($sample);
        $row = Scope::build($sample, 'x', fn(string $m) => throw new TemplateException($m));
        $this->assertSame(Kind::Repeat, $row->kind('roles'));
        $this->assertCount(1, $row->nodes('name'));
    }

    public function testRowRootIsIndexedAsAPlainVar(): void
    {
        $root = Html::parseFragment(\Dom\HTMLDocument::createEmpty(), '<option var="option[]">a</option>');
        $sample = $root->firstElementChild;
        $this->assertNotNull($sample);
        $row = Scope::build($sample, 'x', fn(string $m) => throw new TemplateException($m));
        $this->assertSame(Kind::Var, $row->kind('option'));
        $this->assertSame([$sample], $row->nodes('option'));
    }

    public function testKindConflictThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`x` is used as both');
        $this->scope('<div><p var="x">a</p><p var="x?">b</p></div>');
    }

    public function testTwoSamplesWithOneNameThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->scope('<ul><li var="r[]">a</li><li var="r[]">b</li></ul>');
    }

    public function testTemplateElementContentIsNotIndexed(): void
    {
        $this->assertNull($this->scope('<div><template x-for="i in l"><p var="inside">x</p></template></div>')->kind('inside'));
    }

    public function testLenientSkipsTheConflictingNode(): void
    {
        $msgs = [];
        $root = Html::parseFragment(\Dom\HTMLDocument::createEmpty(), '<div><p var="x">a</p><p var="x?">b</p><p var="a b">c</p></div>');
        $s = Scope::build($root, 'test', function (string $m) use (&$msgs): void { $msgs[] = $m; });
        $this->assertSame(Kind::Var, $s->kind('x'));
        $this->assertCount(1, $s->nodes('x'));
        $this->assertCount(2, $msgs);
    }

    private function scope(string $html): Scope
    {
        $root = Html::parseFragment(\Dom\HTMLDocument::createEmpty(), $html);
        return Scope::build($root, 'test', fn(string $m) => throw new TemplateException($m));
    }
}
