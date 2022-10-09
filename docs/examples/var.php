<?php
// Start the output buffer
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>PHP Dom Template (PDT) Library - example01.html</title>
  <link rel="stylesheet" type="text/css" href="stylesheet.css" />
</head>
<body>
  <div id="content">
    <h1>Hello World</h1>
    <p var="helloWorld">Default Text</p>

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
$path = dirname(dirname(dirname(__FILE__)));
include_once $path . '/Dom/Exception.php';
include_once $path . '/Dom/Template.php';


// Create a template from the html in the buffer
$buff = trim(ob_get_clean());

$template = \Dom\Template::load($buff);
$template->setText('helloWorld', 'This is the `Hello World` Dynamic text.');
echo $template->toString();
?>