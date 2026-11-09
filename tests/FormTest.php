<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Form\Form;
use Tk\DomTemplate\Form\Select;
use Tk\DomTemplate\Parser;
use Tk\DomTemplate\Parser\FormParser;
use Tk\DomTemplate\Template;

class FormTest extends TestCase
{
    private FormParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FormParser();
        Parser::instance()->registerParser($this->parser);
    }

    private function load(string $formAttrs = 'id="f"'): Template
    {
        return Template::load(<<<HTML
<form $formAttrs method="post">
  <input type="text" name="name"/>
  <input type="checkbox" name="agree" value="1"/>
  <select name="country"></select>
  <select name="colours[]" multiple="multiple"></select>
  <textarea name="comments"></textarea>
</form>
HTML);
    }

    public function testFormIsFoundById(): void
    {
        $this->load();
        $this->assertInstanceOf(Form::class, $this->parser->getForm('f'));
    }

    public function testFormIsFoundByNameWhenItHasNoId(): void
    {
        $this->load('name="contact"');
        $this->assertInstanceOf(Form::class, $this->parser->getForm('contact'));
    }

    public function testSetValues(): void
    {
        $t = $this->load();
        $form = $this->parser->getForm('f');

        $form->getFormElement('name')->setValue('Bob');
        $form->getFormElement('comments')->setValue('Hi <there>');
        $form->getFormElement('agree')->setChecked(true);
        $xp = $this->render($t);

        $this->assertSame('Bob', $xp->query('//input[@name="name"]/@value')->item(0)->nodeValue);
        $this->assertSame(['Hi <there>'], $this->texts($xp, '//textarea'));
        $this->assertCount(1, $xp->query('//input[@name="agree"][@checked]'));
    }

    public function testSelectOptionsAndSelection(): void
    {
        $t = $this->load();
        $form = $this->parser->getForm('f');

        /** @var Select $country */
        $country = $form->getFormElement('country');
        $country->appendOption('Australia', 'AU');
        $country->appendOption('New Zealand', 'NZ');
        $country->setValue('NZ');

        /** @var Select $colours */
        $colours = $form->getFormElement('colours[]');
        foreach (['red', 'green', 'blue'] as $c) {
            $colours->appendOption(ucfirst($c), $c);
        }
        $colours->setValue(['red', 'blue']);

        $this->assertSame('NZ', $country->getValue());
        $this->assertSame(['red', 'blue'], $colours->getValue());
        $xp = $this->render($t);

        $this->assertSame(['Australia', 'New Zealand'], $this->texts($xp, '//select[@name="country"]/option'));
        $this->assertSame(['New Zealand'], $this->texts($xp, '//select[@name="country"]/option[@selected]'));
        $this->assertSame(['Red', 'Blue'], $this->texts($xp, '//select[@name="colours[]"]/option[@selected]'));
    }
}
