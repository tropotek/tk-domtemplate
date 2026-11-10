<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Modifier;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\Context;
use Tk\DomTemplate\Template;

/**
 * Fixes from the phase 6 review
 */
class ModifierFixTest extends TestCase
{
    private function toc(): BaseModifier
    {
        // Collects headings, then writes a list into <nav>: the tkapd Toc pattern
        return new class extends BaseModifier {
            public function selector(): string { return 'h2'; }
            public function element(\Dom\Element $el, Context $ctx): void
            {
                $ctx->store($this)['headings'] = [...($ctx->store($this)['headings'] ?? []), (string)$el->textContent];
            }
            public function done(Context $ctx): void
            {
                $nav = $ctx->root->querySelector('nav');
                if ($nav) {
                    $nav->textContent = implode(',', $ctx->store($this)['headings'] ?? []);
                }
            }
        };
    }

    public function testPerRenderStateDoesNotLeakAcrossRendersOnASharedEngine(): void
    {
        $engine = new Engine(modifier: new Modifier([$this->toc()]));
        $this->assertSame('<nav>A,B</nav><h2>A</h2><h2>B</h2>', $engine->load('<nav></nav><h2>A</h2><h2>B</h2>')->render());
        $this->assertSame('<nav>C</nav><h2>C</h2>', $engine->load('<nav></nav><h2>C</h2>')->render());
    }

    public function testNestedRenderInsideAModifierHasItsOwnState(): void
    {
        $engine = null;
        $inner = new class extends BaseModifier {
            public ?Engine $engine = null;
            public function selector(): string { return 'aside'; }
            public function element(\Dom\Element $el, Context $ctx): void
            {
                // Render another template with the same engine in the middle of the pass
                $el->innerHTML = (string)$this->engine?->load('<nav></nav><h2>Inner</h2>')->render();
            }
        };
        $engine = new Engine(modifier: new Modifier([$this->toc(), $inner]));
        $inner->engine = $engine;
        $out = $engine->load('<nav></nav><h2>Outer</h2><aside></aside>')->render();
        $this->assertStringStartsWith('<nav>Outer</nav>', $out);
        $this->assertStringContainsString('<aside><nav>Inner</nav><h2>Inner</h2></aside>', $out);
    }

    public function testStoreIsPerModifier(): void
    {
        $doc = \Dom\HTMLDocument::createEmpty();
        $ctx = new Context($doc, $doc, false);
        $a = $this->toc();
        $b = $this->toc();
        $ctx->store($a)['x'] = 1;
        $this->assertFalse(isset($ctx->store($b)['x']));
        $this->assertSame(1, $ctx->store($a)['x']);
    }
}
