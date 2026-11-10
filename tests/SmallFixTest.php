<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Place;
use Tk\DomTemplate\Renderer\Renderer;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Small user-visible fixes from the pre-release review
 */
class SmallFixTest extends TestCase
{
    private function page(): Template
    {
        return Template::load('<!doctype html><html><head></head><body></body></html>');
    }

    // Asset attributes

    public function testAssetAttrsAcceptAlpineNames(): void
    {
        $out = Template::load('<p></p>')->js('a();', attrs: ['@load' => 'x', ':class' => 'y'])->render();
        $this->assertSame('<p></p><script @load="x" :class="y">a();</script>', $out);
    }

    public function testAssetAttrsAcceptAListOfBareNames(): void
    {
        $out = $this->page()->jsUrl('/a.js', ['defer', 'type' => 'module'])->render();
        $this->assertStringContainsString('<script src="/a.js" defer="" type="module"></script>', $out);
    }

    public function testAssetAttrNameStillValidated(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Invalid attribute name `a b`');
        Template::load('<p></p>')->css('p{}', attrs: ['a b']);
    }

    // Renderer clone after render

    public function testCloningARendererAfterRenderMakesAFreshTemplate(): void
    {
        $r = new class extends Renderer {
            public int $made = 0;
            protected function makeTemplate(): Template
            {
                $this->made++;
                return Template::load('<p var="x">a</p>');
            }
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $r->getTemplate()?->text('x', 'first')->render();
        $c = clone $r;
        $this->assertSame('<p>second</p>', $c->getTemplate()?->text('x', 'second')->render());
        $this->assertSame(2, $c->made);
    }

    // meta() with property and http-equiv

    public function testMetaProperty(): void
    {
        $out = $this->page()->meta('og:title', 'Hello', attr: 'property')->render();
        $this->assertStringContainsString('<meta property="og:title" content="Hello">', $out);
    }

    public function testMetaHttpEquivReplacesTheLayoutTag(): void
    {
        $t = Template::load('<!doctype html><html><head><meta http-equiv="Refresh" content="5"></head><body></body></html>');
        $out = $t->meta('refresh', '10', attr: 'http-equiv')->render();
        $this->assertSame(1, substr_count($out, '<meta '));
        $this->assertStringContainsString('<meta http-equiv="refresh" content="10">', $out);
    }

    public function testMetaNameAndPropertyDoNotReplaceEachOther(): void
    {
        $out = $this->page()->meta('description', 'n')->meta('description', 'p', attr: 'property')->render();
        $this->assertStringContainsString('<meta name="description" content="n">', $out);
        $this->assertStringContainsString('<meta property="description" content="p">', $out);
    }

    public function testMetaRejectsOtherAttributes(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('meta() attr must be name, property, http-equiv or itemprop');
        $this->page()->meta('x', 'y', attr: 'charset');
    }

    public function testMetaInFragmentIsInline(): void
    {
        $out = Template::load('<p></p>')->meta('og:x', 'y', attr: 'property')->js('a();', Place::Head)->render();
        $this->assertSame('<p></p><meta property="og:x" content="y"><script>a();</script>', $out);
    }
}
