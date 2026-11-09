<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::loadFile(__DIR__ . '/templates/var.html');

// Text is escaped
$content->setText('greeting', 'Hello <World> & friends');

// HTML is parsed and inserted
$content->setHtml('html', '<strong>Bold</strong> and <em>italic</em> markup, inserted as nodes.');

// append/prepend keep the existing content
$content->appendText('list', ' + appended');
$content->prependText('list', 'prepended + ');

// Attributes and CSS classes
$content->setAttr('link', 'href', 'https://github.com/tropotek/tk-domtemplate');
$content->setAttr('link', ['target' => '_blank', 'rel' => 'noopener']);
$content->addCss('alert', 'alert-success');
$content->removeCss('alert', 'alert-secondary');
$content->setText('alert', 'This alert changed from grey to green with addCss() and removeCss().');

// Every node sharing a var name is updated together
$content->setText('total', '42');

renderPage('Vars', $content, ['var.php', 'templates/var.html']);
