<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Modifier;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\ClearComments;
use Tk\DomTemplate\Modifier\Context;
use Tk\DomTemplate\Template;

class ClearCommentsTest extends TestCase
{
    public function testEngineRunsModifierOnPageAndFragment(): void
    {
        $e = new Engine(modifier: new Modifier([new ClearComments()]));
        $page = $e->load('<main var="c"><!-- page --></main>');
        $page->html('c', Template::load('<p>x<!-- child --></p>'));
        $this->assertSame('<main><p>x</p></main>', $page->render());
        $this->assertSame('<div x-data="{}" @click="a()">k</div>', $e->load('<div x-data="{}" @click="a()"><!-- c -->k</div>')->render());
    }

    public function testFullPageCommentsRemoved(): void
    {
        $e = new Engine(modifier: new Modifier([new ClearComments()]));
        $out = $e->load("<!doctype html>\n<html><head><!-- h --></head><body><!-- b --><p>x</p></body></html>")->render();
        $this->assertStringNotContainsString('<!--', $out);
    }

    public function testModifierRunsOnceWhenRenderedTwice(): void
    {
        $count = new class extends BaseModifier {
            public int $n = 0;
            public function selector(): string { return 'p'; }
            public function element(\Dom\Element $el, Context $ctx): void { $this->n++; }
        };
        $t = (new Engine(modifier: new Modifier([$count])))->load('<p>a</p>');
        $t->render();
        $t->render();
        $this->assertSame(1, $count->n);
    }

    public function testInsertedChildrenAreNotProcessedSeparately(): void
    {
        $count = new class extends BaseModifier {
            public int $n = 0;
            public function selector(): string { return 'p'; }
            public function element(\Dom\Element $el, Context $ctx): void { $this->n++; }
        };
        $e = new Engine(modifier: new Modifier([$count]));
        $page = $e->load('<div var="c"></div>');
        $page->html('c', $e->load('<p>child</p>'));
        $page->render();
        $this->assertSame(1, $count->n);
    }

    public function testModifierSeesPlacedAssetsAndFragmentFlag(): void
    {
        $seen = new class extends BaseModifier {
            public ?bool $fragment = null;
            public int $scripts = 0;
            public function selector(): string { return 'script'; }
            public function element(\Dom\Element $el, Context $ctx): void { $this->scripts++; $this->fragment = $ctx->fragment; }
        };
        $page = (new Engine(modifier: new Modifier([$seen])))->load('<!doctype html><html><head></head><body></body></html>');
        $page->jsUrl('/a.js');
        $page->render();
        $this->assertSame([1, false], [$seen->scripts, $seen->fragment]);
    }

    public function testScriptsAddedByAModifierGetTheNonce(): void
    {
        $adds = new class extends BaseModifier {
            public function selector(): string { return 'main'; }
            public function element(\Dom\Element $el, Context $ctx): void {
                $s = $ctx->document->createElement('script');
                $s->textContent = 'added();';
                $el->append($s);
            }
        };
        $page = (new Engine(nonce: 'n', modifier: new Modifier([$adds])))->load('<main>x</main>');
        $this->assertSame('<main>x<script nonce="n">added();</script></main>', $page->render());
    }
}
