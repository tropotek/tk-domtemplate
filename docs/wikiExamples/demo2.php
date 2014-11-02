<?php
/**
 * Demo 1
 * 
 * @author Michael Mifsud
 * @copyright (c)2011 Tropotek
 * @see http://www.tropotek.com/
 */

// include the Template lib
include_once dirname(__FILE__) . '/../../Dom/Template.php';
// Create a template from the html in demo1.html
$template = Dom_Template::load('demo2.html');
// Add some content
$template->setChoice('text1');
// Parse and display the template
echo $template->toString();
?>