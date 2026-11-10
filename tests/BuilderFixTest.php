<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Builder;
use Tk\DomTemplate\Engine;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes from the phase 7 review
 */
class BuilderFixTest extends TestCase
{
    public function testDuplicateNameInOneFileThrowsWhenStrict(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Component `dup` is defined more than once in dup.html');
        Builder::load(__DIR__ . '/fixtures/dup.html');
    }

    public function testDuplicateNameInOneFileKeepsTheFirstWhenLenient(): void
    {
        $log = new SpyLogger();
        $lib = Builder::load(__DIR__ . '/fixtures/dup.html', new Engine(strict: false, logger: $log));
        $this->assertSame('<div>first</div>', $lib->get('dup')->render());
        $this->assertCount(1, $log->lines);
    }

    public function testExistsIsExact(): void
    {
        $lib = Builder::load([__DIR__ . '/fixtures/base.html', __DIR__ . '/fixtures/override.html']);
        $this->assertTrue($lib->exists('form.input'));
        $this->assertFalse($lib->exists('form.hidden'));
        $this->assertTrue($lib->has('form.hidden'));
    }

    public function testMatchSaysWhichComponentANameResolvesTo(): void
    {
        $lib = Builder::load([__DIR__ . '/fixtures/base.html', __DIR__ . '/fixtures/override.html']);
        $this->assertSame('form.input', $lib->match('form.input.email'));
        $this->assertSame('form', $lib->match('form.hidden'));
        $this->assertNull($lib->match('nope'));
    }
}
