<?php
namespace Dom;

use PHPUnit\Framework\TestCase;
use \Dom\Template as Template;

/**
 * @author Tropotek <http://www.tropotek.com/>
 */
class TemplateTest extends TestCase
{
    /**
     * @var string
     */
    public $tplStr = '';

    /**
     * @var string
     */
    public $tplStr1 = '';


    /**
     *
     *
     */
    public function __construct()
    {
        parent::__construct('Dom Template Test');

        $this->tplStr = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1 var="title"></h1>
  <div var="content">
    <p>Existing Content</p>
  </div>
</body>
</html>
HTML;
        $this->tplStr1 = <<<HTML
<ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul>
HTML;


    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {

    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void
    {

    }

    /**
     * Test basic template features
     * Load, Attr, text
     *
     */
    public function test_domTemplateLoadFile()
    {
        // test the load file and string methods
        $tpl = Template::loadFile(dirname(__FILE__).'/data/test.html');
        $this->assertTrue($tpl instanceof Template, '\Dom\Template::loadFile()');
    }


    /**
     * Test basic template features
     * Load, Attr, text
     *
     */
    public function test_domTemplateBasic()
    {
        // test the load file and string methods
        $tpl = Template::loadFile(dirname(__FILE__).'/data/test.html');


        $tpl = Template::load($this->tplStr);
        $tpl->setText('title', 'Test Title');
        $tpl->setAttr('title', 'id', 'title');

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1 id="title">Test Title</h1>
  <div>
    <p>Existing Content</p>
  </div>
</body>
</html>
HTML;
        $this->assertEquals(trim($result1), trim($tpl->toString()), 'insertText(), setAttr()');

    }


    /**
     * Test
     *
     */
    public function test_domTemplateInsertTemplate()
    {
        $tpl = Template::load($this->tplStr);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <div><ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul></div>
</body>
</html>
HTML;

        $tpl1 = Template::load($this->tplStr1);
        $tpl->insertTemplate('content', $tpl1);
        $this->assertEquals(trim($result1), trim($tpl->toString()), 'insertTemplate()');
    }

    /**
     * Test
     *
     */
    public function test_domTemplateInsertHtml()
    {

        $tpl = Template::load($this->tplStr);
        $tpl->insertHtml('content', $this->tplStr1);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <div><ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul></div>
</body>
</html>
HTML;

        $this->assertEquals(trim($result1), trim($tpl->toString()), 'insertHtml()');
    }

    /**
     * Test
     *
     */
    public function test_domTemplateReplaceTemplate()
    {
        $tpl = Template::load($this->tplStr);
        $tpl1 = Template::load($this->tplStr1);
        $tpl->replaceTemplate('content', $tpl1);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul>
</body>
</html>
HTML;

        $this->assertEquals(trim($result1), trim($tpl->toString()), 'replaceTemplate()');

    }

    /**
     * Test
     *
     */
    public function test_domTemplateReplaceHtml()
    {
        $tpl = Template::load($this->tplStr);
        $tpl->replaceHtml('content', $this->tplStr1);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul>
</body>
</html>
HTML;
        $this->assertEquals(trim($result1), trim($tpl->toString()), 'replaceHtml()');

    }

    /**
     * Test
     *
     */
    public function test_domTemplateAppendTemplate()
    {
        $tpl = Template::load($this->tplStr);
        $tpl1 = Template::load($this->tplStr1);
        $tpl->appendTemplate('content', $tpl1);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <div>
    <p>Existing Content</p>
  <ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul></div>
</body>
</html>
HTML;

        $this->assertEquals(trim($result1), trim($tpl->toString()), 'appendTemplate()');
    }

    /**
     * Test
     *
     */
    public function test_domTemplateAppendHtml()
    {
        $tpl = Template::load($this->tplStr);
        $tpl->appendHtml('content', $this->tplStr1);

        $result1 = <<<HTML
<html>
<head>
  <title>Dom Site</title>
</head>
<body>
  <h1></h1>
  <div>
    <p>Existing Content</p>
  <ul>
  <li>List Item 1</li>
  <li>List Item 2</li>
  <li>List Item 3</li>
</ul></div>
</body>
</html>
HTML;

        $this->assertEquals(trim($result1), trim($tpl->toString()), 'appendHtml()');
    }


}