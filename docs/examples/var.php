<?php
// Start the output buffer
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>PHP Dom Template (PDT) Library - example01.html</title>
</head>
<body>
  <div id="content">
    <h1>Hello World</h1>
    <p var="helloWorld">Default Text</p>

    <p>&#160;</p>

    <div class="footer">
      <p class="home"><a href="index.html">Home</a></p>
      <p class="copyright"><a href="http://www.tropotek.com" target="_blank">Copyright 2008 PHP DOMTemplate</a></p>
    </div>
  </div>
</body>
</html>
<?php
// Include lib, you should use use composer if available.
$basepath = dirname(__FILE__, 3);
include_once $basepath . '/vendor/autoload.php';


// Create a template from the html in the buffer
$buff = trim(ob_get_clean());

$template = \Dom\Template::load($buff);

$template->appendCssUrl('stylesheet.css');
//$template->appendJs('alert();');
$template->setText('helloWorld', 'This is the `Hello World` Dynamic text.');

echo $template->toString();
?>