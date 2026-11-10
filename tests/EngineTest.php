<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\TemplateException;

class EngineTest extends TestCase
{
    public function testStrictFailThrows(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('boom');
        (new Engine())->fail('boom');
    }

    public function testLenientFailLogsWarning(): void
    {
        $log = new SpyLogger();
        (new Engine(strict: false, logger: $log))->fail('boom');
        $this->assertSame([['warning', 'boom']], $log->lines);
    }

    public function testLenientFailWithoutLoggerUsesErrorLog(): void
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'log');
        $old = ini_set('error_log', $file);
        (new Engine(strict: false))->fail('boom');
        ini_set('error_log', (string)$old);
        $this->assertStringContainsString('tk-domtemplate: boom', (string)file_get_contents($file));
        unlink($file);
    }

    public function testEngineLoadsWithItself(): void
    {
        $e = new Engine(strict: false);
        $this->assertSame($e, $e->load('<p>x</p>')->engine());
    }

    public function testLenientEngineLogsMarkupMistakesAndRenders(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<div><p var="a b">x</p><p var="ok">y</p></div>');
        $this->assertSame('<div><p>x</p><p>y</p></div>', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testTemplatesWithoutEngineUseStrictDefault(): void
    {
        $this->assertTrue(\Tk\DomTemplate\Template::load('<p>x</p>')->engine()->strict);
    }
}
