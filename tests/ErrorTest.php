<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

class ErrorTest extends TestCase
{
    public function testUnknownNameSuggests(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('No var `titel` in (string). Did you mean `title`?');
        Template::load('<h1 var="title">x</h1>')->text('titel', 'y');
    }

    public function testUnknownNameWithoutCloseMatch(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/^No var `zzz` in \(string\)\.$/');
        Template::load('<h1 var="title">x</h1>')->var('zzz');
    }

    public function testWrongKind(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`users` is a repeat in (string), use repeat()');
        Template::load('<ul><li var="users[]">u</li></ul>')->var('users');
    }

    public function testFileLabelInMessage(): void
    {
        $path = sys_get_temp_dir() . '/v9-' . uniqid() . '.html';
        file_put_contents($path, '<p var="a">x</p>');
        try {
            Template::file($path)->var('b');
            $this->fail('expected exception');
        } catch (TemplateException $e) {
            $this->assertStringContainsString("in $path", $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function testLenientEngineLogsAndContinues(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<h1 var="title">x</h1>');
        $t->var('titel')->text('ignored')->addClass('a')->show();
        $this->assertSame('<h1>x</h1>', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testLenientWrongKindDoesNothing(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<ul><li var="users[]">u</li></ul>');
        $t->text('users', 'x');
        $this->assertSame('<ul></ul>', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testHasNeverFails(): void
    {
        $this->assertFalse(Template::load('<p>x</p>')->has('nope'));
    }

    public function testChangeAfterRenderThrowsEvenWhenLenient(): void
    {
        $t = (new Engine(strict: false))->load('<p var="a">a</p>');
        $h = $t->var('a');
        $t->render();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Template (string) has already been rendered');
        $h->text('late');
    }

    public function testShortcutAfterRenderThrows(): void
    {
        $t = Template::load('<p var="a?">a</p>');
        $t->render();
        $this->expectException(\LogicException::class);
        $t->show('a');
    }

    public function testUnknownNameAfterRenderStillThrowsLogicException(): void
    {
        $t = (new Engine(strict: false, logger: new SpyLogger()))->load('<p>a</p>');
        $t->render();
        $this->expectException(\LogicException::class);
        $t->text('nope', 'x');
    }
}
