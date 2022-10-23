<?php
// Start the output buffer
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>PHP Dom Template (PDT) Library - example01.html</title>
  <link rel="stylesheet" type="text/css" href="stylesheet.css" />
</head>
<body>
  <div id="content">
    <h1>Hello World</h1>
    <p var="helloWorld">Default Text</p>
    <p>&#160;</p>

    <h2>Sub Template</h2>
    <div var="subTemplate"></div>

    <p>&#160;</p>

    <div class="footer">
      <p class="home"><a href="index.html">Home</a></p>
      <p class="copyright"><a href="http://www.domtemplate.com" target="_blank">Copyright 2008 PHP DOMTemplate</a></p>
    </div>
  </div>
</body>
</html>
<?php
// Include lib, you should use use composer if available.
$path = dirname(__FILE__, 3);
include_once $path . '/Dom/Exception.php';
include_once $path . '/Dom/Template.php';

// Create a template from the html in the buffer
$buff = ob_get_clean();

$template = \Dom\Template::load($buff);
$template->setText('helloWorld', 'This is the `Hello World` Dynamic text.');
// Create some css styles
$css = <<<CSS
body {font-size: 80%; background-color: #CCC; }
p { background-color: #9CF; }
CSS;
// Append the styles to the head tag
$template->appendCss($css);

// This is how we append a javascript file
$template->appendJsUrl('/js/jquery.js');

// Create Sub Template
$html = <<<HTML
<?xml version="1.0" encoding="UTF-8"?>
<div class="subTemplate">
  <pre>
This is some sub template content.
This is some sub template content.
This is some sub template content.
This is some sub template content.
 </pre>
</div>
HTML;
$subTpl = \Dom\Template::load($html);
// Add some headers to the sub template
$css = <<<CSS
.subTemplate { background-color: #44C; }
pre { background-color: #CFC; color: #333; border: 1px dashed #CCC; }
CSS;
// Append the sub template styles to template
$subTpl->appendCss($css);
// Append a url to a sub template
$subTpl->appendJsUrl('/js/jquery2.js');

// Now we add the sub template to the parent template
$template->insertTemplate('subTemplate', $subTpl);

echo $template->toString();
?>