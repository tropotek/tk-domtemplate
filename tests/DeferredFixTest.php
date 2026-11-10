<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Builder;
use Tk\DomTemplate\Engine;
use Tk\DomTemplate\ModifierList;
use Tk\DomTemplate\Modifier\BaseModifier;
use Tk\DomTemplate\Modifier\Context;
use Tk\DomTemplate\Renderer\Renderer;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes for the minor items deferred from the phase reviews
 */
class DeferredFixTest extends TestCase
{
    /** @var list<string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            @unlink($path);
        }
    }

    private function libFile(string $html): string
    {
        $path = sys_get_temp_dir() . '/v9-lib-' . uniqid() . '.html';
        file_put_contents($path, $html);
        $this->tmp[] = $path;
        return $path;
    }

    // "Did you mean"

    public function testNoSuggestionForAShortName(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/^No var `x` in \(string\)\.$/');
        Template::load('<p var="id">a</p>')->var('x');
    }

    public function testOneEditSuggestedForAThreeLetterName(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Did you mean `name`?');
        Template::load('<p var="name">a</p>')->var('nme');
    }

    public function testTwoEditsNotSuggestedForAFourLetterName(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/^No var `tilt` in \(string\)\.$/');
        Template::load('<p var="title">a</p>')->var('tilt');
    }

    // Names inside overwritten content

    public function testNameInsideOverwrittenContainerFails(): void
    {
        $t = Template::load('<div var="box"><p var="msg">a</p></div>');
        $t->html('box', 'new');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`msg` in (string) was inside content that has been replaced');
        $t->text('msg', 'x');
    }

    public function testNameInsideOverwrittenContainerLogsWhenLenient(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<div var="box"><p var="msg">a</p></div><p var="msg">b</p>');
        $t->html('box', 'new');
        $t->text('msg', 'x');
        $this->assertSame('<div>new</div><p>x</p>', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testRepeatInsideOverwrittenContainerFails(): void
    {
        $t = Template::load('<div var="box"><ul><li var="r[]">a</li></ul></div>');
        $t->html('box', 'new');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('`r` in (string) was inside content that has been replaced');
        $t->repeat('r');
    }

    public function testReplacedNameStillWorks(): void
    {
        $t = Template::load('<p var="a">x</p>');
        $t->replace('a', '<b>y</b>');
        $t->addClass('a', 'c');
        $this->assertSame('<b class="c">y</b>', $t->render());
    }

    // attr() with one argument

    public function testAttrWithoutValueSetsBareAttribute(): void
    {
        $t = Template::load('<input var="a">');
        $t->var('a')->attr('disabled');
        $this->assertSame('<input disabled="">', $t->render());
    }

    public function testTemplateAttrShortcutWithoutValueSetsBareAttribute(): void
    {
        $t = Template::load('<input var="a">');
        $t->attr('a', 'required');
        $this->assertSame('<input required="">', $t->render());
    }

    public function testAttrWithExplicitNullStillRemoves(): void
    {
        $t = Template::load('<input var="a" disabled>');
        $t->attr('a', 'disabled', null);
        $this->assertSame('<input>', $t->render());
    }

    // <tk-slot>

    public function testUserWrittenSlotElementIsKept(): void
    {
        $t = Template::load('<div var="a"></div><tk-slot>keep</tk-slot>');
        $t->html('a', Template::load('<i>in</i>'));
        $this->assertSame('<div><i>in</i></div><tk-slot>keep</tk-slot>', $t->render());
    }

    public function testUserWrittenSlotInsideChildIsKept(): void
    {
        $page = Template::load('<div var="a"></div>');
        $page->html('a', Template::load('<tk-slot>keep</tk-slot>'));
        $this->assertSame('<div><tk-slot>keep</tk-slot></div>', $page->render());
    }

    // Rows as insert children

    public function testRowCannotBeInserted(): void
    {
        $t = Template::load('<div var="a"></div><ul><li var="r[]">x</li></ul>');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A row cannot be inserted as a template; insert it with insert()');
        $t->html('a', $t->repeat('r'));
    }

    // Circular inserts

    public function testFailedCircularInsertLeavesSiblingsUnbuilt(): void
    {
        $page = Template::load('<div var="a"></div><div var="b"></div>');
        $sibling = Template::load('<p var="p">s</p>');
        $loop = Template::load('<div var="x"></div>');
        $page->html('a', $sibling);
        $page->html('b', $loop);
        $loop->html('x', $page);
        try {
            $page->render();
            $this->fail('expected a circular insert error');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Circular template insert', $e->getMessage());
        }
        $this->assertFalse($sibling->isRendered());
        $this->assertFalse($page->isRendered());
        $sibling->text('p', 'still mutable');
    }

    public function testCycleInsideHiddenContentIsNotAnError(): void
    {
        $page = Template::load('<div var="a?"></div><p>ok</p>');
        $loop = Template::load('<div var="x"></div>');
        $page->html('a', $loop);
        $loop->html('x', $page);
        $this->assertSame('<p>ok</p>', $page->render());
    }

    // Asset attribute names

    public function testBadAssetAttrNameFailsAtTheCall(): void
    {
        $t = Template::load('<p>a</p>');
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Invalid attribute name `bad name`');
        $t->js('x()', attrs: ['bad name' => 'y']);
    }

    public function testBadJsUrlAttrNameFailsAtTheCall(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<p>a</p>')->jsUrl('/a.js', ['a"b' => 'y']);
    }

    // Children rendered on their own

    public function testInsertingARenderedTemplateThrows(): void
    {
        $child = Template::load('<p>c</p>');
        $child->render();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Template (string) was rendered on its own; insert it before rendering');
        Template::load('<div var="a"></div>')->html('a', $child);
    }

    public function testRenderingAnInsertedChildAloneMakesThePageThrow(): void
    {
        $page = Template::load('<div var="a"></div>');
        $child = Template::load('<p>c</p>')->css('p{}', \Tk\DomTemplate\Place::Head);
        $page->html('a', $child);
        $child->render();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('was rendered on its own');
        $page->render();
    }

    public function testChildBuiltInOnePageCanBeReusedInAnother(): void
    {
        $child = Template::load('<p>c</p>');
        $one = Template::load('<div var="a"></div>');
        $one->html('a', $child);
        $one->render();
        $two = Template::load('<section var="a"></section>');
        $two->html('a', $child);
        $this->assertSame('<section><p>c</p></section>', $two->render());
    }

    // Modifiers

    public function testModifierSkipsDescendantsOfRemovedNodes(): void
    {
        $mod = new class extends BaseModifier {
            /** @var list<string> */
            public array $seen = [];
            public function selector(): string { return 'div, p'; }
            public function element(\Dom\Element $el, Context $ctx): void
            {
                $this->seen[] = $el->tagName . '.' . $el->getAttribute('class');
                if ($el->classList->contains('drop')) {
                    $el->remove();
                }
            }
            public function comment(\Dom\Comment $comment, Context $ctx): void
            {
                $this->seen[] = 'comment:' . $comment->data;
            }
        };
        $t = (new Engine(modifiers: new ModifierList([$mod])))->load('<div class="drop"><p class="in"></p><!--gone--></div><p class="out"></p><!--kept-->');
        $this->assertSame('<p class="out"></p><!--kept-->', $t->render());
        $this->assertSame(['DIV.drop', 'P.out', 'comment:kept'], $mod->seen);
    }

    public function testRenderAfterModifierExceptionThrowsInsteadOfRerunning(): void
    {
        $mod = new class extends BaseModifier {
            public int $calls = 0;
            public function done(Context $ctx): void
            {
                $this->calls++;
                throw new \RuntimeException('boom');
            }
        };
        $t = (new Engine(modifiers: new ModifierList([$mod])))->load('<p>a</p>');
        try {
            $t->render();
            $this->fail('expected the modifier to throw');
        } catch (\RuntimeException) {
        }
        try {
            $t->render();
            $this->fail('expected a LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('failed to render', $e->getMessage());
        }
        $this->assertSame(1, $mod->calls);
    }

    // Builder

    public function testBuilderStripsOptionsOnNestedComponents(): void
    {
        $lib = Builder::load($this->libFile('<div data-tpl="card" data-opt-a="1"><span data-tpl="card.badge" data-opt-b="2">b</span></div>'));
        $this->assertSame('<div><span>b</span></div>', $lib->get('card')->render());
        $this->assertSame(['b' => '2'], $lib->options('card.badge'));
    }

    public function testBuilderReportsMarkupErrorsOnceAtLoad(): void
    {
        $log = new SpyLogger();
        $lib = Builder::load(
            $this->libFile('<ul data-tpl="bad"><li var="r[]">a</li><li var="r[]">b</li></ul>'),
            new Engine(strict: false, logger: $log),
        );
        $this->assertCount(1, $log->lines);
        $lib->get('bad');
        $lib->get('bad');
        $this->assertCount(1, $log->lines);
    }

    public function testBuilderGetsAreIndependent(): void
    {
        $lib = Builder::load($this->libFile('<p data-tpl="p" var="t">a</p>'));
        $one = $lib->get('p')->text('t', 'one');
        $two = $lib->get('p');
        $this->assertSame('<p>one</p>', $one->render());
        $this->assertSame('<p>a</p>', $two->render());
    }

    public function testBuilderTrimsNames(): void
    {
        $lib = Builder::load($this->libFile('<p data-tpl="p">a</p>'));
        $this->assertSame('<p>a</p>', $lib->get(' p ')->render());
        $this->assertTrue($lib->exists(' p'));
    }

    public function testBuilderNeedsAFile(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Builder::load() needs at least one file');
        Builder::load([]);
    }

    // Renderers

    public function testMakeTemplateReturningNullIsNotRetried(): void
    {
        $r = new class extends Renderer {
            public int $made = 0;
            protected function makeTemplate(): ?Template
            {
                $this->made++;
                return null;
            }
            public function show(): ?Template { return $this->getTemplate(); }
        };
        $r->getTemplate();
        $r->getTemplate();
        $this->assertSame(1, $r->made);
    }

    // URL assets

    public function testPreloadLinkDoesNotCountAsAStylesheet(): void
    {
        $t = Template::load('<!doctype html><html><head><link rel="preload" href="/a.css" as="style"></head><body></body></html>');
        $t->cssUrl('/a.css');
        $this->assertStringContainsString('<link rel="stylesheet" href="/a.css">', $t->render());
    }
}
