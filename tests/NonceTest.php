<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Place;
use Tk\DomTemplate\Template;

class NonceTest extends TestCase
{
    public function testNonceOnAuthoredAndAddedScriptsAndStyles(): void
    {
        $e = new Engine(nonce: 'abc');
        $page = $e->load('<div><script>authored();</script><script src="/x.js"></script><main var="c"></main></div>');
        $page->html('c', Template::load('<p>p</p>')->js('added();')->css('.a{}'));
        $out = $page->render();
        $this->assertSame(4, substr_count($out, 'nonce="abc"'));
        $this->assertStringContainsString('<script src="/x.js" nonce="abc"></script>', $out);
    }

    public function testInheritedByChildLoadedWithoutEngine(): void
    {
        $page = (new Engine(nonce: 'n1'))->load('<main var="c"></main>');
        $page->html('c', Template::load('<p>x</p>')->js('k();'));
        $this->assertStringContainsString('<script nonce="n1">k();</script>', $page->render());
    }

    public function testExistingNonceIsKept(): void
    {
        $page = (new Engine(nonce: 'new'))->load('<div><script nonce="old">a();</script></div>');
        $this->assertSame('<div><script nonce="old">a();</script></div>', $page->render());
    }

    public function testNonceOnHeadAndEndAssetsOfAFullPage(): void
    {
        $page = (new Engine(nonce: 'z'))->load('<!doctype html><html><head></head><body></body></html>');
        $page->js('h();', Place::Head)->css('.e{}', Place::End);
        $out = $page->render();
        $this->assertStringContainsString('<head><script nonce="z">h();</script></head>', $out);
        $this->assertStringContainsString('<style nonce="z">.e{}</style></body>', $out);
    }

    public function testNoNonceByDefault(): void
    {
        $this->assertStringNotContainsString('nonce', Template::load('<div>x</div>')->js('a();')->render());
    }
}
