<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Builder;
use Tk\DomTemplate\Engine;
use Tk\DomTemplate\TemplateException;

class BuilderTest extends TestCase
{
    private function lib(): Builder
    {
        return Builder::load([__DIR__ . '/fixtures/base.html', __DIR__ . '/fixtures/override.html']);
    }

    public function testLaterFilesOverrideWholeComponents(): void
    {
        $out = $this->lib()->get('form.input')->render();
        $this->assertStringContainsString('form-control-sm', $out);
        $this->assertStringNotContainsString('<label', $out);
    }

    public function testUnoverriddenComponentsComeFromTheBase(): void
    {
        $this->assertTrue($this->lib()->has('form.select'));
        $this->assertStringContainsString('form-select', $this->lib()->get('form.select')->render());
    }

    public function testDottedFallback(): void
    {
        $this->assertStringContainsString('form-control-sm', $this->lib()->get('form.input.email')->render());
        $this->assertTrue($this->lib()->has('form.input.email'));
    }

    public function testUnknownComponentThrowsEvenWhenLenient(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('No component `nope.thing` in base.html, override.html');
        Builder::load([__DIR__ . '/fixtures/base.html', __DIR__ . '/fixtures/override.html'], new Engine(strict: false))->get('nope.thing');
    }

    public function testHasIsFalseForUnknown(): void
    {
        $this->assertFalse($this->lib()->has('nope'));
    }

    public function testOptionsAndStripping(): void
    {
        $lib = $this->lib();
        $this->assertSame(['error-css' => 'is-invalid'], $lib->options('form'));
        $this->assertSame([], $lib->options('form.input'));
        $out = $lib->get('form')->render();
        $this->assertStringNotContainsString('data-tpl', $out);
        $this->assertStringNotContainsString('data-opt-', $out);
        $this->assertStringContainsString('<form class="tk-form" method="post">', $out);
    }

    public function testNestedComponentMarkersAreStripped(): void
    {
        $out = $this->lib()->get('card')->render();
        $this->assertStringNotContainsString('data-tpl', $out);
        $this->assertStringContainsString('<div class="badge">badge</div>', $out);
        $this->assertSame('<div class="badge">badge</div>', $this->lib()->get('card.badge')->render());
    }

    public function testTableRowComponent(): void
    {
        $t = $this->lib()->get('table.row');
        $t->text('cell', 'x');
        $this->assertSame('<tr><td>x</td></tr>', $t->render());
    }

    public function testEachGetIsAFreshTemplate(): void
    {
        $lib = $this->lib();
        $a = $lib->get('form.input');
        $a->attr('element', 'name', 'x');
        $a->render();
        $this->assertStringNotContainsString('name="x"', $lib->get('form.input')->render());
    }

    public function testRepeatsInsideComponents(): void
    {
        $t = $this->lib()->get('form.select');
        $t->each('option', ['a' => 'A', 'b' => 'B'], fn($row, $label, $value) => $row->text('option', $label)->attr('option', 'value', $value));
        $this->assertStringContainsString('<select class="form-select"><option value="a">A</option><option value="b">B</option></select>', $t->render());
    }

    public function testComponentsUseTheBuilderEngineAndLabel(): void
    {
        $e = new Engine(strict: false);
        $t = Builder::load(__DIR__ . '/fixtures/base.html', $e)->get('form');
        $this->assertSame($e, $t->engine());
        $this->assertSame('base.html#form', $t->label());
    }

    public function testUnreadableFileThrows(): void
    {
        $this->expectException(TemplateException::class);
        Builder::load('/nope/missing.html');
    }
}
