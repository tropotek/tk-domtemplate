<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Engine;
use Tk\DomTemplate\Template;

class FromNodeTest extends TestCase
{
    private function doc(string $body): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString("<!doctype html><html><body>$body</body></html>", LIBXML_NOERROR, 'UTF-8');
    }

    public function testTableRowNodeKeepsItsStructure(): void
    {
        $src = $this->doc('<table><tbody><tr var="row" class="r"><td var="c">x</td></tr></tbody></table>');
        $t = Template::fromNode($src->querySelector('tr') ?? throw new \RuntimeException(), label: 'lib#row');
        $t->text('c', 'y');
        $this->assertSame('<tr class="r"><td>y</td></tr>', $t->render());
        $this->assertSame('lib#row', $t->label());
        $this->assertTrue($t->isFragment());
    }

    public function testOptionNode(): void
    {
        $src = $this->doc('<select><option var="o">a</option></select>');
        $this->assertSame('<option>a</option>', Template::fromNode($src->querySelector('option') ?? throw new \RuntimeException())->render());
    }

    public function testSourceDocumentIsUntouched(): void
    {
        $src = $this->doc('<p var="p">a</p>');
        $p = $src->querySelector('p') ?? throw new \RuntimeException();
        Template::fromNode($p)->text('p', 'changed')->render();
        $this->assertSame('a', $p->textContent);
    }

    public function testTemplateContentAndAlpineSurvive(): void
    {
        $src = $this->doc('<div x-data="{o:1}" @click="o++"><template x-for="i in l"><li :key="i"></li></template></div>');
        $this->assertSame('<div x-data="{o:1}" @click="o++"><template x-for="i in l"><li :key="i"></li></template></div>',
            Template::fromNode($src->querySelector('div') ?? throw new \RuntimeException())->render());
    }

    public function testEngineAndRepeatsWork(): void
    {
        $e = new Engine(strict: false);
        $src = $this->doc('<ul><li var="r[]">x</li></ul>');
        $t = Template::fromNode($src->querySelector('ul') ?? throw new \RuntimeException(), $e);
        $t->repeat('r')->append();
        $this->assertSame($e, $t->engine());
        $this->assertSame('<ul><li>x</li></ul>', $t->render());
    }
}
