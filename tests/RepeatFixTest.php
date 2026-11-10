<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;
use Tk\DomTemplate\TemplateException;

/**
 * Fixes from the phase 3 review
 */
class RepeatFixTest extends TestCase
{
    public function testTemplateContentSurvivesRowClone(): void
    {
        $t = Template::load('<ul><li var="r[]" x-data="{items:[1,2]}"><template x-for="i in items"><span x-text="i"></span></template></li></ul>');
        $t->repeat('r')->insert();
        $this->assertSame('<ul><li x-data="{items:[1,2]}"><template x-for="i in items"><span x-text="i"></span></template></li></ul>', $t->render());
    }

    public function testNestedTemplateContentSurvivesInNestedRows(): void
    {
        $t = Template::load('<div><section var="s[]"><p var="p[]"><template x-if="a"><template x-for="b in c"><i></i></template></template></p></section></div>');
        $s = $t->repeat('s');
        $s->repeat('p')->insert();
        $s->insert();
        $this->assertSame('<div><section><p><template x-if="a"><template x-for="b in c"><i></i></template></template></p></section></div>', $t->render());
    }

    public function testTemplateContentSurvivesInEveryAppendToTarget(): void
    {
        $t = Template::load('<div><ul var="l"></ul><ul var="l"></ul><li var="r[]"><template x-if="o"><b>x</b></template></li></div>');
        $t->repeat('r')->insertInto('l');
        $this->assertSame(2, substr_count($t->render(), '<template x-if="o"><b>x</b></template>'));
    }

    public function testMarkupMistakeInsideSampleThrowsOnLoad(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Invalid var name `bad name`');
        Template::load('<ul><li var="r[]"><b var="bad name">x</b></li></ul>');
    }

    public function testNestedSampleMistakeThrowsOnLoad(): void
    {
        $this->expectException(TemplateException::class);
        Template::load('<table><tr var="tr[]"><td var="td[]"><i var="x">a</i><i var="x?">b</i></td></tr></table>');
    }

    public function testLenientSampleMistakeIsLoggedOnceNotPerRow(): void
    {
        $log = new SpyLogger();
        $t = (new Engine(strict: false, logger: $log))->load('<ul><li var="r[]"><b var="bad name">x</b><i var="ok?">s</i></li></ul>');
        foreach ([1, 2, 3] as $i) {
            $t->repeat('r')->insert();
        }
        $this->assertSame('<ul><li><b>x</b></li><li><b>x</b></li><li><b>x</b></li></ul>', $t->render());
        $this->assertCount(1, $log->lines);
    }

    public function testReplacingARowsOwnElementThrows(): void
    {
        $row = Template::load('<ul><li var="r[]">x</li></ul>')->repeat('r');
        $this->expectException(\LogicException::class);
        $row->replace('r', '<li>new</li>');
    }

    public function testRowUsesItsParentsEngine(): void
    {
        $e = new Engine(strict: false);
        $row = $e->load('<ul><li var="r[]">x</li></ul>')->repeat('r');
        $this->assertSame($e, $row->engine());
    }
}
