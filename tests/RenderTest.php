<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

class RenderTest extends TestCase
{
    public function testFragmentStripsMarkersRemovesOptionalsAndSamples(): void
    {
        $t = Template::load('<div><h1 var="title">T</h1><p var="admin?">secret</p><ul><li var="users[]">u</li></ul></div>');
        $this->assertTrue($t->isFragment());
        $this->assertSame('<div><h1>T</h1><ul></ul></div>', $t->render());
    }

    public function testFullPage(): void
    {
        $t = Template::load("<!doctype html>\n<html lang=\"en\"><head><title>x</title></head><body><p var=\"a\">a</p></body></html>");
        $this->assertFalse($t->isFragment());
        $out = $t->render();
        $this->assertStringStartsWith('<!DOCTYPE html>', $out);
        $this->assertStringContainsString('<p>a</p>', $out);
    }

    public function testAlpineAndHtmxAttributesRoundTrip(): void
    {
        $html = '<div x-data="{open:false}"><button @click="open=!open" :class="{a:open}" hx-get="/x" hx-target="#t">b</button>'
            . '<template x-for="i in items"><li x-text="i"></li></template><my-el hidden="">c</my-el></div>';
        $this->assertSame($html, Template::load($html)->render());
    }

    public function testEncodingSurvives(): void
    {
        $this->assertSame('<p>é 日本 😀&nbsp;x</p>', Template::load('<p>é 日本 😀&nbsp;x</p>')->render());
    }

    public function testRenderTwiceIsIdenticalAndLocks(): void
    {
        $t = Template::load('<p var="a">a</p>');
        $first = $t->render();
        $this->assertTrue($t->isRendered());
        $this->assertSame($first, $t->render());
        $this->assertSame($first, (string)$t);
    }

    public function testFileLabelAndMissingFile(): void
    {
        $path = sys_get_temp_dir() . '/v9-' . uniqid() . '.html';
        file_put_contents($path, '<p>x</p>');
        $this->assertSame($path, Template::file($path)->label());
        $this->assertSame('(string)', Template::load('<p>x</p>')->label());
        unlink($path);
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Cannot read template file: /nope/missing.html');
        Template::file('/nope/missing.html');
    }

    public function testMarkupMistakeThrowsOnLoad(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Invalid var name `a b` in (string)');
        Template::load('<p var="a b">x</p>');
    }
}
