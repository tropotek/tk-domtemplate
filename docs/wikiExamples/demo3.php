<?php
/**
 * Demo 3
 * 
 * @author Michael Mifsud
 * @copyright (c)2011 Tropotek
 * @see http://www.tropotek.com/
 */

// include the Template lib
include_once dirname(__FILE__) . '/../../Dom/Template.php';
// Create a template from the html in demo1.html
$template = Dom_Template::load('demo3.html');

// Create some tabliture data
$listData = array(
    'http://www.tropotek.com/' => 'Tropotek Home Page', 
    'http://www.phpdruid.com/' => 'PHPDruid Home Page', 
    'http://www.domtemplate.com' => 'Php Dom Template'
);

// Check if there is data in the list and show the unordered list
if (count($listData) > 0) {
    $template->setChoice('list');
}
// Render each list item and its url
foreach ($listData as $url => $value) {
    $repeat = $template->getRepeat('row');
    $repeat->replaceText('url', $value);
    $repeat->replaceAttr('url', 'href', $url);
    $repeat->replaceAttr('url', 'title', $value);
    $repeat->replaceAttr('url', 'target', '_blank');
    $repeat->append();
}

// Parse and display the template
echo $template->toString();
?>