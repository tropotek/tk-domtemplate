<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Renderer\DisplayInterface;
use Tk\DomTemplate\Renderer\Renderer;
use Tk\DomTemplate\Renderer\RendererInterface;
use Tk\DomTemplate\Renderer\RendererTrait;
use Tk\DomTemplate\Template;

class RendererTest extends TestCase
{
    public function testLazyMakeTemplate(): void
    {
        $r = new class extends Renderer {
            public int $made = 0;
            protected function makeTemplate(): Template
            {
                $this->made++;
                return Template::load('<p var="x">a</p>');
            }
            public function show(): ?Template
            {
                return $this->getTemplate()?->text('x', 'b');
            }
        };
        $this->assertSame('<p>b</p>', $r->show()?->render());
        $r->getTemplate();
        $this->assertSame(1, $r->made);
        $this->assertTrue($r->hasTemplate());
    }

    public function testNoTemplateByDefault(): void
    {
        $r = new class extends Renderer {
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $this->assertNull($r->show());
        $this->assertFalse($r->hasTemplate());
    }

    public function testCloneCopiesTheTemplate(): void
    {
        $r = new class extends Renderer {
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $r->setTemplate(Template::load('<p var="x">a</p>'));
        $c = clone $r;
        $c->getTemplate()?->text('x', 'changed');
        $this->assertSame('<p>a</p>', $r->getTemplate()?->render());
        $this->assertSame('<p>changed</p>', $c->getTemplate()?->render());
    }

    public function testTraitForClassesThatCannotExtend(): void
    {
        $r = new class implements RendererInterface {
            use RendererTrait;
            protected function makeTemplate(): Template { return Template::load('<i>t</i>'); }
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $this->assertInstanceOf(DisplayInterface::class, $r);
        $this->assertSame('<i>t</i>', $r->show()?->render());
    }
}
