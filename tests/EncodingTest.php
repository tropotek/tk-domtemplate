<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Place;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Output is always valid UTF-8, and templates can't declare another charset
 */
class EncodingTest extends TestCase
{
    private const string BAD = "bad \xC3\x28 \xFF end";
    private const string FIXED = "bad \u{FFFD}( \u{FFFD} end";

    public function testTextReplacesInvalidUtf8(): void
    {
        $t = Template::load('<p var="p"></p>');
        $t->text('p', self::BAD);
        $this->assertSame('<p>' . self::FIXED . '</p>', $t->render());
    }

    public function testAttrReplacesInvalidUtf8(): void
    {
        $t = Template::load('<a var="a"></a>');
        $t->attr('a', 'title', self::BAD);
        $this->assertSame('<a title="' . self::FIXED . '"></a>', $t->render());
    }

    public function testHtmlStringReplacesInvalidUtf8(): void
    {
        $t = Template::load('<p var="p"></p>');
        $t->html('p', '<b>' . self::BAD . '</b>');
        $this->assertSame('<p><b>' . self::FIXED . '</b></p>', $t->render());
    }

    public function testAssetsReplaceInvalidUtf8(): void
    {
        $t = Template::load('<!doctype html><html><head></head><body></body></html>');
        $t->title(self::BAD)->meta('d', self::BAD)->js('x("' . self::BAD . '");', Place::Head);
        $out = $t->render();
        $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
        $this->assertSame(3, substr_count($out, self::FIXED));
    }

    public function testValidTextIsUnchanged(): void
    {
        $t = Template::load('<p var="p"></p>');
        $t->text('p', 'café &amp; 😀');
        $this->assertSame('<p>café &amp;amp; 😀</p>', $t->render());
    }

    public function testNonUtf8MetaCharsetIsReported(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('(string) declares charset iso-8859-1; templates are read and written as UTF-8');
        Template::load('<!doctype html><html><head><meta charset="iso-8859-1"></head><body></body></html>');
    }

    public function testNonUtf8HttpEquivCharsetIsReported(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('declares charset windows-1252');
        Template::load('<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1252"></head><body></body></html>');
    }

    public function testLenientRewritesTheCharsetToUtf8(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<!doctype html><html><head><meta charset="ISO-8859-1"></head><body></body></html>');
        $this->assertStringContainsString('<meta charset="utf-8">', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testUtf8CharsetIsAccepted(): void
    {
        $out = Template::load('<!doctype html><html><head><meta charset="UTF-8"><meta http-equiv="content-type" content="text/html; charset=utf8"></head><body></body></html>')->render();
        $this->assertStringContainsString('<meta charset="UTF-8">', $out);
    }
}
