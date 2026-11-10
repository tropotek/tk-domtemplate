<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Html;
use Tk\DomTemplate\ModifierList;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\Context;

class ModifierTest extends TestCase
{
    public function testRunsInOrderEachSeeingThePreviousOutput(): void
    {
        $first = new class extends BaseModifier {
            public function selector(): string { return 'a[href^="page://"]'; }
            public function element(\Dom\Element $el, Context $ctx): void { $el->setAttribute('href', '/' . substr((string)$el->getAttribute('href'), 7)); }
        };
        $second = new class extends BaseModifier {
            public function selector(): string { return 'a[href^="/"]'; }
            public function element(\Dom\Element $el, Context $ctx): void { $el->classList->add('internal'); }
        };
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><html><body><a href="page://home">h</a><a href="http://x">x</a></body></html>', LIBXML_NOERROR, 'UTF-8');
        (new ModifierList([$first, $second]))->process(new Context($doc, $doc, false));
        $this->assertStringContainsString('<a href="/home" class="internal">h</a><a href="http://x">x</a>', $doc->saveHtml());
    }

    public function testNullSelectorSeesEveryElementAndCommentsAndDone(): void
    {
        $spy = new class extends BaseModifier {
            public int $els = 0;
            public int $comments = 0;
            public bool $done = false;
            public function element(\Dom\Element $el, Context $ctx): void { $this->els++; }
            public function comment(\Dom\Comment $c, Context $ctx): void { $this->comments++; }
            public function done(Context $ctx): void { $this->done = true; }
        };
        $doc = \Dom\HTMLDocument::createEmpty();
        $root = Html::parseFragment($doc, '<p>a<!-- c --></p><b>b</b>');
        (new ModifierList())->with($spy)->process(new Context($doc, $root, true));
        $this->assertSame([2, 1, true], [$spy->els, $spy->comments, $spy->done]);
    }

    public function testRemovingNodesDuringIterationIsSafe(): void
    {
        $rm = new class extends BaseModifier {
            public function selector(): string { return 'i'; }
            public function element(\Dom\Element $el, Context $ctx): void { $el->remove(); }
        };
        $doc = \Dom\HTMLDocument::createEmpty();
        $root = Html::parseFragment($doc, '<i>1</i><i>2</i><b>k</b>');
        (new ModifierList([$rm]))->process(new Context($doc, $root, true));
        $this->assertSame('<b>k</b>', $root->innerHTML);
    }

    public function testWithReturnsANewList(): void
    {
        $m = new ModifierList();
        $spy = new class extends BaseModifier { public function element(\Dom\Element $el, Context $ctx): void {} };
        $this->assertNotSame($m, $m->with($spy));
        $this->assertCount(0, $m->modifiers);
        $this->assertCount(1, $m->with($spy)->modifiers);
    }

    public function testCommentsOnAFullPage(): void
    {
        $spy = new class extends BaseModifier {
            /** @var list<string> */
            public array $seen = [];
            public function selector(): string { return 'never-matches'; }
            public function element(\Dom\Element $el, Context $ctx): void {}
            public function comment(\Dom\Comment $c, Context $ctx): void { $this->seen[] = trim((string)$c->data); }
        };
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><html><head><!-- h --></head><body><!-- b --><p><!-- p --></p></body></html>', LIBXML_NOERROR, 'UTF-8');
        (new ModifierList([$spy]))->process(new Context($doc, $doc, false));
        $this->assertSame(['h', 'b', 'p'], $spy->seen);
    }
}
