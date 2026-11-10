<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

/**
 * Fixes from the phase 4 review
 */
class CompositionFixTest extends TestCase
{
    public function testCloneFullPageWithAttributesInTemplateContent(): void
    {
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $code = "require $autoload;" . <<<'PHP'
$c = Tk\DomTemplate\Template::load('<!doctype html><html><body><template x-for="i in l"><li class="i" :key="i"></li></template></body></html>');
echo (clone $c)->render();
PHP;
        // Run in a separate process: the bug was a segfault
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, implode("\n", $out));
        $this->assertStringContainsString('<template x-for="i in l"><li class="i" :key="i"></li></template>', implode("\n", $out));
    }

    public function testChildInHiddenNodeIsNotBuilt(): void
    {
        $page = Template::load('<div><p var="a?"></p><b>k</b></div>');
        $child = Template::load('<i>never</i>');
        $page->html('a', $child);
        $page->render();
        $this->assertFalse($child->isRendered());
    }

    public function testChildInOverwrittenContainerIsNotBuilt(): void
    {
        $page = Template::load('<div var="outer"><p var="inner"></p></div>');
        $child = Template::load('<i>x</i>');
        $page->html('inner', $child)->html('outer', 'gone');
        $this->assertSame('<div>gone</div>', $page->render());
        $this->assertFalse($child->isRendered());
    }

    public function testCycleInDiscardedMarkupDoesNotThrow(): void
    {
        $page = Template::load('<div var="outer"><p var="inner"></p></div>');
        $page->html('inner', $page)->html('outer', 'safe');
        $this->assertSame('<div>safe</div>', $page->render());
    }

    public function testChildInUnusedSampleIsNotBuilt(): void
    {
        $page = Template::load('<ul><li var="r[]"><b var="c"></b></li></ul>');
        $child = Template::load('<i>x</i>');
        $row = $page->repeat('r');
        $row->html('c', $child);
        $row->hide('r');
        $row->insert();
        $this->assertSame('<ul></ul>', $page->render());
        $this->assertFalse($child->isRendered());
    }

    public function testAttributesAfterTemplateReplaceApplyToTheReplacement(): void
    {
        $page = Template::load('<div><p var="d">x</p></div>');
        $page->replace('d', Template::load('<i>r</i>'))->addClass('d', 'k')->attr('d', 'data-z', '1');
        $this->assertSame('<div><i class="k" data-z="1">r</i></div>', $page->render());
    }

    public function testClassesMergeWithTheReplacementsOwn(): void
    {
        $page = Template::load('<div><p var="d">x</p></div>');
        $page->var('d')->replace(Template::load('<section class="card">c</section><b>2</b>'))->addClass('mt-2');
        $this->assertSame('<div><section class="card mt-2">c</section><b class="mt-2">2</b></div>', $page->render());
    }
}
