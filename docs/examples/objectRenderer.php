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
    <h1>\Dom\Renderer\AutoRenderer Example</h1>

    <p>
      <em>
        NOTICE: This object is currently under development and in an unstable state.
        More Documentation will be available once this object is completed.
      </em>
    </p>


    <h4>Raw Template</h4>
    <pre var="tpl"></pre>
    <hr/>
    <h4>Parsed Template</h4>
    <pre var="parsed"></pre>

    <div class="footer">
      <p class="home"><a href="index.html">Home</a></p>
      <p class="copyright"><a href="http://www.domtemplate.com" target="_blank">Copyright 2008 PHP DOMTemplate</a></p>
    </div>
  </div>
</body>
</html>
<?php
// Include lib, you should use use composer if available.
$path = dirname(dirname(dirname(__FILE__)));
include_once $path . '/Dom/Exception.php';
include_once $path . '/Dom/Template.php';
include_once $path . '/Dom/Renderer/Iface.php';
include_once $path . '/Dom/Renderer/AutoRenderer.php';

// Create a template from the html in the buffer
$buff = ob_get_clean();
$template = \Dom\Template::load($buff);

// Bootstrap Template example
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
$subTpl = \Dom\Template::load($xml);
$objRen = new \Dom\Renderer\AutoRenderer($subTpl);



// Fill object renderer with objects
$objRen->set('title', 'This is a title');
$objRen->set('descr', '<p>Nullam et sapien lectus, et pretium orci? In fermentum magna a
    justo vestibulum congue. Sed mi nisi, egestas ut interdum nec, convallis vitae mauris.</p>');
$list1 = array();
$list1[] = array('title' => 'Item 1 Test', 'descr' => 'Item 1 description');
$list1[] = array('title' => 'Item 2 Test', 'descr' => 'Item 2 description');
$list1[] = array('title' => 'Item 3 Test', 'descr' => 'Item 3 description');
$objRen->set('list1', $list1);

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
$objRen->set('list2', $list2);
$objRen->set('testChoiceExists', true); // value could be any non-false value 1 'string' etc....

// Show Raw Template
$template->insertHtml('tpl', htmlentities($xml));
// Execute Object Bootstrap
$objRen->show();
// Show parsed template
$template->insertHtml('parsed', htmlentities($objRen->getTemplate()->toString()));

echo $template->toString();
