<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/var.html');

// text() escapes; html() parses markup into the node
$content->text('greeting', 'Hello <World> & friends');
$content->html('markup', '<strong>Bold</strong> and <em>italic</em> markup, inserted as nodes.');

// append and prepend keep the existing content
$content->var('list')->append(' + appended')->prepend('prepended + ');

// One handle, several changes: attributes (true = empty attribute) and classes
$content->var('link')
    ->attr(['href' => 'https://github.com/tropotek/tk-domtemplate', 'target' => '_blank', 'rel' => 'noopener'])
    ->attr('download', false);
$content->var('alert')
    ->removeClass('alert-secondary')
    ->addClass('alert-success')
    ->text('This alert changed from grey to green with removeClass() and addClass().');

// Every node sharing a var name is updated together
$content->text('total', '42');

// Plain nodes can be hidden; optional (?) nodes are hidden until shown
$content->hide('draft');
$content->show('note');

renderPage('Vars', $content->render(), ['var.php', 'templates/var.html']);
