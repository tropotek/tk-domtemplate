<?php
// Start the output buffer
ob_start();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>PHP Dom Template (PDT) Library - ObjectRenderer.html</title>
  <link rel="stylesheet" type="text/css" href="stylesheet.css" />
</head>
<body>
  <div id="content">
    <h1>Dom_ObjectRenderer Example</h1>

    <h4>Raw Template</h4>
    <pre var="tpl"></pre>
    <hr/>
    <h4>Parsed Template</h4>
    <pre var="parsed"></pre>

  </div>
</body>
</html>
<?php
// include the Template lib
include_once dirname(dirname(dirname(__FILE__))) . '/lib/Dom/Template.php';
include_once dirname(dirname(dirname(__FILE__))) . '/lib/Dom/RendererInterface.php';
include_once dirname(dirname(dirname(__FILE__))) . '/lib/Dom/ObjectRenderer.php';

// Create a template from the html in the buffer
$buff = ob_get_clean();
$template = Dom_Template::load($buff);




// Renderer Template example
$xml = <<<TPL
<?xml version="1.0" encoding="UTF-8"?>
<div>

  <h4 var="title"></h4>
  <div var="HTML:descr"></div>

  <h5>Repeat array example</h5>
  <table class="table">
    <tr repeat="list1">
      <td var="['title']"></td>
      <td var="HTML:['descr']"></td>
    </tr>
  </table>
  <hr/>

  <h5>Repeat object example</h5>
  <table class="table">
    <tr repeat="list2">
      <td var="ucfirst:title"></td>
      <td var="HTML:descr"></td>
    </tr>
  </table>
  <hr/>

  <h5>Array Access and Multiple var Example</h5>
  <div><strong var="list1[0]['title'] list1[1]['title']"></strong>: <span var="list1[0]['descr']"></span></div>
  <hr/>

  <h5>Choice Example</h5>
  <div>
    <p choice="testChoiceExists">Choice Exists</p>
    <p choice="testChoiceNoExists">Choice Non-Exists</p>
  </div>

</div>
TPL;

// Create ObjectRenderer
$subTpl = Dom_Template::load($xml);
$objList = array();
$objRen = new Dom_ObjectRenderer($objList, $subTpl);

// Fill object renderer with objects
$objRen->title = 'This is a title';
$objRen->descr = '<p>Nullam et sapien lectus, et pretium orci? In fermentum magna a
    justo vestibulum congue. Sed mi nisi, egestas ut interdum nec, convallis vitae mauris.</p>';
$list1 = array();
$list1[] = array('title' => 'Item 1 Test', 'descr' => 'Item 1 description');
$list1[] = array('title' => 'Item 2 Test', 'descr' => 'Item 2 description');
$list1[] = array('title' => 'Item 3 Test', 'descr' => 'Item 3 description');
$objRen->list1 = $list1;

$list2 = array();
$obj = new stdClass();
$obj->title = 'object title 1';
$obj->descr = '<p>Object description 1</p>';
$list2[] = $obj;
$obj = new stdClass();
$obj->title = 'object title 2';
$obj->descr = '<p>Object description 2</p>';
$list2[] = $obj;
$obj = new stdClass();
$obj->title = 'object title 3';
$obj->descr = '<p>Object <a href="#">description</a> 3</p>';
$list2[] = $obj;
$objRen->list2 = $list2;

$objRen->testChoiceExists = true; // value could be any non-false value 1 'string' etc....

// Show Raw Template
$template->insertHtml('tpl', htmlentities($xml));
// Execute Object Renderer
$objRen->show();
// Show parsed template
$template->insertHtml('parsed', htmlentities($objRen->getTemplate()->toString()));





echo $template->toString();
?>