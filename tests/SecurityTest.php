<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Builder;
use Tk\DomTemplate\Engine;
use Tk\DomTemplate\ModifierList;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\Context;
use Tk\DomTemplate\Place;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * CSP nonce scope, script escaping and raw-text targets
 */
class SecurityTest extends TestCase
{
    /** Parse rendered output the way a browser would */
    private function browse(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);
    }

    // Nonce: only what the template author or the library wrote

    public function testNonceSkipsScriptsInsertedAsHtmlStrings(): void
    {
        $t = (new Engine(nonce: 'N'))->load('<div var="c"></div>');
        $t->html('c', '<script>alert(1)</script><style>p{}</style>');
        $this->assertSame('<div><script>alert(1)</script><style>p{}</style></div>', $t->render());
    }

    public function testNonceSkipsScriptsAppendedAsHtmlStringsToAChild(): void
    {
        $page = (new Engine(nonce: 'N'))->load('<main var="m"></main>');
        $child = Template::load('<p var="p"></p><script>own();</script>');
        $child->append('p', '<script>evil()</script>');
        $page->html('m', $child);
        $this->assertSame('<main><p><script>evil()</script></p><script nonce="N">own();</script></main>', $page->render());
    }

    public function testNonceOnScriptsInRowsFromTheSource(): void
    {
        $t = (new Engine(nonce: 'N'))->load('<ul><li var="r[]"><script>r();</script></li></ul>');
        $t->repeat('r')->insert();
        $this->assertSame('<ul><li><script nonce="N">r();</script></li></ul>', $t->render());
    }

    public function testNonceOnBuilderComponentScripts(): void
    {
        $path = sys_get_temp_dir() . '/v9-sec-' . uniqid() . '.html';
        file_put_contents($path, '<div data-tpl="w"><script>w();</script></div>');
        try {
            $lib = Builder::load($path, new Engine(nonce: 'N'));
            $this->assertSame('<div><script nonce="N">w();</script></div>', $lib->get('w')->render());
        } finally {
            unlink($path);
        }
    }

    public function testNonceOnUrlAssetsAndSourceLinks(): void
    {
        $t = (new Engine(nonce: 'N'))->load('<!doctype html><html><head><link rel="stylesheet" href="/a.css"></head><body></body></html>');
        $t->jsUrl('/x.js')->cssUrl('/b.css');
        $out = $t->render();
        $this->assertStringContainsString('<link rel="stylesheet" href="/a.css" nonce="N">', $out);
        $this->assertStringContainsString('<script src="/x.js" nonce="N"></script>', $out);
        $this->assertStringContainsString('<link rel="stylesheet" href="/b.css" nonce="N">', $out);
    }

    public function testModifierScriptsNeedTrust(): void
    {
        $mod = new class extends BaseModifier {
            public function selector(): string { return 'main'; }
            public function element(\Dom\Element $el, Context $ctx): void
            {
                $mine = $ctx->document->createElement('script');
                $mine->textContent = 'mine();';
                $el->append($ctx->trust($mine));
                $copied = $ctx->document->createElement('script');
                $copied->textContent = 'copied();';
                $el->append($copied);
            }
        };
        $t = (new Engine(nonce: 'N', modifiers: new ModifierList([$mod])))->load('<main></main>');
        $this->assertSame('<main><script nonce="N">mine();</script><script>copied();</script></main>', $t->render());
    }

    public function testTrustMarkerNeverReachesTheOutput(): void
    {
        $out = Template::load('<div><script>a();</script></div>')->js('b();')->render();
        $this->assertSame('<div><script>a();</script></div><script>b();</script>', $out);
    }

    public function testForgedTrustMarkerIsIgnoredAndRemoved(): void
    {
        $t = (new Engine(nonce: 'N'))->load('<div var="c"></div>');
        $t->html('c', '<script data-tk-trust="guess">evil()</script>');
        $this->assertSame('<div><script>evil()</script></div>', $t->render());
    }

    // Nonce per request

    public function testNonceClosureIsReadAtEachRender(): void
    {
        $current = 'one';
        $engine = new Engine(nonce: function () use (&$current): string { return $current; });
        $first = $engine->load('<p></p>')->js('a();')->render();
        $current = 'two';
        $second = $engine->load('<p></p>')->js('a();')->render();
        $this->assertStringContainsString('nonce="one"', $first);
        $this->assertStringContainsString('nonce="two"', $second);
    }

    public function testSharedBuilderUsesTheCurrentNonce(): void
    {
        $path = sys_get_temp_dir() . '/v9-sec-' . uniqid() . '.html';
        file_put_contents($path, '<div data-tpl="w"></div>');
        try {
            $current = 'REQ1';
            $lib = Builder::load($path, new Engine(nonce: function () use (&$current): string { return $current; }));
            $lib->get('w')->js('a();')->render();
            $current = 'REQ2';
            $this->assertStringContainsString('nonce="REQ2"', $lib->get('w')->js('a();')->render());
        } finally {
            unlink($path);
        }
    }

    public function testNonceClosureReturningNullAddsNone(): void
    {
        $out = (new Engine(nonce: fn(): ?string => null))->load('<p></p>')->js('a();')->render();
        $this->assertStringNotContainsString('nonce', $out);
    }

    // Script escaping

    public function testJsCannotEnterTheDoubleEscapedState(): void
    {
        $page = Template::load('<!doctype html><html><head></head><body><p>after</p></body></html>');
        $page->js('var s = ' . json_encode('<!--<script>') . ';', Place::Head);
        $doc = \Dom\HTMLDocument::createFromString($page->render(), LIBXML_NOERROR);
        $this->assertSame('after', $doc->querySelector('body p')?->textContent);
    }

    public function testJsEscapesKeepTheStringValue(): void
    {
        $out = Template::load('<div></div>')->js('x = "<!--<SCRIPT></script>";')->render();
        $this->assertStringContainsString('x = "\u003C!--<SCRIPT><\/script>";', $out);
    }

    // text() on raw-text elements

    public function testTextIntoScriptCannotCloseIt(): void
    {
        $t = Template::load('<script type="application/json" var="d"></script>');
        $t->text('d', '{"a":"</script><img src=x onerror=alert(1)>"}');
        $doc = $this->browse($t->render());
        $this->assertNull($doc->querySelector('img'));
        $this->assertSame('{"a":"<\/script><img src=x onerror=alert(1)>"}', $doc->querySelector('script')?->textContent);
    }

    public function testTextIntoStyleCannotCloseIt(): void
    {
        $t = Template::load('<style var="s"></style>');
        $t->text('s', 'a{} </style><img src=x>');
        $this->assertNull($this->browse($t->render())->querySelector('img'));
    }

    public function testTextIntoUnescapableElementFails(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('text() can\'t escape content for <iframe> `f` in (string)');
        Template::load('<iframe var="f"></iframe>')->text('f', '</iframe><img src=x>');
    }

    public function testTextIntoTextareaIsStillEscaped(): void
    {
        $t = Template::load('<textarea var="t"></textarea>');
        $t->text('t', '</textarea><img src=x>');
        $this->assertNull($this->browse($t->render())->querySelector('img'));
    }
}
