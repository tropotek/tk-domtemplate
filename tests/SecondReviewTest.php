<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\ModifierList;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\Context;
use Tk\DomTemplate\Place;
use Tk\DomTemplate\Renderer\Renderer;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes from the second pre-release review
 */
class SecondReviewTest extends TestCase
{
    private function browse(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);
    }

    // A: JavaScript outside strings is left alone

    public function testJsComparisonWithScriptIdentifierIsUntouched(): void
    {
        $code = 'for (let i=0;i<scripts.length;i++){} if (a<script.x) {}';
        $this->assertSame('<p></p><script>' . $code . '</script>', Template::load('<p></p>')->js($code)->render());
    }

    public function testJsEscapesStayValidInJson(): void
    {
        $json = json_encode(['a' => '<!--<script></script>']);
        $out = Template::load('<p></p>')->js('var d = ' . $json . ';')->render();
        $doc = $this->browse($out);
        $this->assertCount(1, $doc->querySelectorAll('script'));
        $code = (string)$doc->querySelector('script')?->textContent;
        $this->assertSame(['a' => '<!--<script></script>'], json_decode(substr($code, 8, -1), true));
    }

    // B: key markers can't be forged

    public function testForgedKeyMarkerDoesNotRemoveAKeyedAsset(): void
    {
        $t = Template::load('<div var="c"></div>');
        $t->html('c', '<span data-tk-key="js:csrf">u</span>');
        $t->js('initCsrf()', key: 'csrf');
        $this->assertSame('<div><span>u</span></div><script>initCsrf()</script>', $t->render());
    }

    public function testKeyedAssetsInASharedChildAreStillDeduplicated(): void
    {
        $child = Template::load('<p>c</p>')->js('k()', key: 'k');
        $page = Template::load('<div var="a"></div><div var="b"></div>');
        $page->html('a', $child)->html('b', $child);
        $this->assertSame(1, substr_count($page->render(), 'k()'));
    }

    // C: the trust secret never reaches a modifier

    public function testModifierNeverSeesTheTrustMarker(): void
    {
        $mod = new class extends BaseModifier {
            public function selector(): string { return 'script'; }
            public function element(\Dom\Element $el, Context $ctx): void
            {
                $el->parentElement?->setAttribute('data-src', $el->outerHTML);
            }
        };
        $t = (new Engine(nonce: 'N', modifiers: new ModifierList([$mod])))->load('<div><script>a()</script></div>');
        $out = $t->render();
        $this->assertStringNotContainsString('data-tk-trust', $out);
        $this->assertStringContainsString('<script nonce="N">a()</script>', $out);
    }

    public function testGetAttrHidesInternalMarkers(): void
    {
        $t = Template::load('<script var="s">a()</script>');
        $this->assertNull($t->var('s')->getAttr('data-tk-trust'));
    }

    // D: non-JS script types

    public function testTextIntoTemplateScriptKeepsItsMarkup(): void
    {
        $t = Template::load('<script type="text/x-template" var="t"></script>');
        $t->text('t', '<div><script-like>x</script-like></div>');
        $this->assertSame('<script type="text/x-template"><div><script-like>x</script-like></div></script>', $t->render());
    }

    public function testTextIntoTemplateScriptRefusesUnsafeSequences(): void
    {
        $t = Template::load('<script type="text/x-template" var="t"></script>');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage("text() can't safely write `<!--` or `</script` into <script type=\"text/x-template\"> `t` in (string)");
        $t->text('t', '<div><!-- note --></div>');
    }

    public function testTextIntoJsonScriptIsEscaped(): void
    {
        $t = Template::load('<script type="application/ld+json" var="d"></script>');
        $t->text('d', '{"a":"</script>"}');
        $doc = $this->browse($t->render());
        $this->assertCount(1, $doc->querySelectorAll('body *'));
        $this->assertSame(['a' => '</script>'], json_decode((string)$doc->querySelector('script')?->textContent, true));
    }

    // E: UTF-8 and NUL

    public function testClassNamesAreScrubbed(): void
    {
        $t = Template::load('<p var="p"></p>');
        $t->addClass('p', "k\xFF");
        $this->assertSame("<p class=\"k\u{FFFD}\"></p>", $t->render());
    }

    public function testNonUtf8AttributeNameIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<p var="p"></p>')->attr('p', "a\xFF", 'x');
    }

    public function testNulIsReplacedNotTruncated(): void
    {
        $t = Template::load('<p var="p"></p>');
        $t->text('p', "before\0after")->attr('p', 'title', "x\0y");
        $this->assertSame("<p title=\"x\u{FFFD}y\">before\u{FFFD}after</p>", $t->render());
    }

    // F: engine inheritance

    public function testChildFirstInEnginelessWrapperFollowsALaterPage(): void
    {
        $child = Template::load('<p>c</p>');
        Template::load('<div var="w"></div>')->html('w', $child);
        $page = (new Engine(strict: false))->load('<main var="m"></main>');
        $page->html('m', $child);
        $this->assertFalse($child->engine()->strict);
    }

    public function testChildDoesNotKeepItsFirstPageAlive(): void
    {
        $child = Template::load('<p>c</p>');
        $page = (new Engine(strict: false))->load('<main var="m"></main>');
        $page->html('m', $child);
        $page->render();
        $ref = \WeakReference::create($page);
        unset($page);
        gc_collect_cycles();
        $this->assertNull($ref->get());
    }

    // G: string append/prepend on <template>

    public function testStringAppendAndPrependOnTemplateElement(): void
    {
        $t = Template::load('<template var="t"><i>mid</i></template>');
        $t->append('t', '<b>end</b>')->prepend('t', '<b>start</b>');
        $this->assertSame('<template><b>start</b><i>mid</i><b>end</b></template>', $t->render());
    }

    // J: edge cases

    public function testEmptyMetaCharsetIsNotReported(): void
    {
        $out = Template::load('<!doctype html><html><head><meta charset=""></head><body></body></html>')->render();
        $this->assertStringContainsString('<meta charset="">', $out);
    }

    public function testNonStringListAttrIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<!doctype html><html><head></head><body></body></html>')->jsUrl('/a.js', [true]);
    }

    public function testInsertFirstRenames(): void
    {
        $t = Template::load('<ul var="out"></ul><ol><li var="r[]"><b var="n"></b></li></ol>');
        $t->repeat('r')->text('n', '1')->insert();
        $t->repeat('r')->text('n', '0')->insertFirst();
        $t->repeat('r')->text('n', 'x')->insertFirstInto('out');
        $this->assertSame('<ul><li><b>x</b></li></ul><ol><li><b>0</b></li><li><b>1</b></li></ol>', $t->render());
    }

    public function testRendererCloneWithRenderedSetTemplateHasNone(): void
    {
        $r = new class extends Renderer {
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $r->setTemplate(Template::load('<p>a</p>'));
        $r->getTemplate()?->render();
        $this->assertNull((clone $r)->getTemplate());
    }
}
