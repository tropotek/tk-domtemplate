<?php
namespace Tk\DomTemplate\Tests;

use Tk\DomTemplate\Template;

class HeadAssetsTest extends TestCase
{
    private function page(): Template
    {
        return Template::load("<!doctype html>\n<html><head><title>Old</title></head><body><div var=\"content\"></div></body></html>");
    }

    public function testHeadHelpersWriteToHead(): void
    {
        $t = $this->page();
        $t->setTitleText('New');
        $t->appendMetaTag('description', 'Desc');
        $t->appendCssUrl('/site.css');
        $t->appendJsUrl('/site.js', ['defer' => 'defer']);
        $t->appendHeadCss('p{color:red}');
        $t->appendHeadJs('let x = 1;');
        $xp = $this->render($t);

        $this->assertSame(['New'], $this->texts($xp, '/html/head/title'));
        $this->assertSame('Desc', $xp->query('/html/head/meta[@name="description"]/@content')->item(0)->nodeValue);
        $this->assertCount(1, $xp->query('/html/head/link[@href="/site.css"]'));
        $this->assertCount(1, $xp->query('/html/head/script[@src="/site.js"][@defer]'));
        $this->assertStringContainsString('p{color:red}', $xp->query('/html/head/style')->item(0)->textContent);
        $this->assertStringContainsString('let x = 1;', $xp->query('/html/head/script[not(@src)]')->item(0)->textContent);
    }

    public function testInlineCssAndJsGoToTheBody(): void
    {
        $t = $this->page();
        $t->appendCss('.a{}');
        $t->appendJs('let y = 2;');
        $xp = $this->render($t);

        $this->assertCount(0, $xp->query('/html/head/style'));
        $this->assertCount(1, $xp->query('/html/body/style'));
        $this->assertCount(1, $xp->query('/html/body/script'));
    }

    public function testInsertedTemplateHeadAssetsMoveUpToThePage(): void
    {
        $page = $this->page();
        $content = Template::load('<section><p var="msg">x</p></section>');
        $content->setText('msg', 'Inserted');
        $content->appendCssUrl('/widget.css');
        $content->appendMetaTag('author', 'Me');
        $page->insertTemplate('content', $content);
        $xp = $this->render($page);

        $this->assertSame(['Inserted'], $this->texts($xp, '//div/section/p'));
        $this->assertCount(1, $xp->query('/html/head/link[@href="/widget.css"]'));
        $this->assertCount(1, $xp->query('/html/head/meta[@name="author"]'));
    }

    public function testDoctypeIsKept(): void
    {
        $this->assertStringStartsWith('<!doctype html>', strtolower($this->page()->toString()));
    }
}
