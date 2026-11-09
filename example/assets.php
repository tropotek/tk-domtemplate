<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::loadFile(__DIR__ . '/templates/assets.html');

// appendMetaTag(), appendHeadCss(), appendHeadJs() and the *Url() methods add to <head>.
// Called on a content template, they move up into the page <head> when it is inserted.
$content->appendMetaTag('description', 'DomTemplate head assets example');
$content->appendHeadCss('.demo-box { border-left: .25rem solid var(--bs-primary); }');

// appendCss() and appendJs() write inline into the template itself (end of <body>, or its root element)
$content->appendJs("document.getElementById('jsTarget').textContent = 'Set by JavaScript added with appendJs().';");

// A sub-template (think "widget") keeps its inline CSS with its markup
$widget = Template::load(<<<HTML
<div class="widget p-3 rounded">
  <strong var="widgetTitle">Widget</strong>
  <p class="mb-0">Its inline appendCss() style stays next to its markup.</p>
</div>
HTML);
$widget->setText('widgetTitle', 'A sub-template widget');
$widget->appendCss('.widget { background: var(--bs-info-bg-subtle); }');
$content->insertTemplate('widget', $widget);

// Render a standalone template to show what the head helpers output
$demo = Template::load('<html><head><title>Demo</title></head><body><p>Hi</p></body></html>');
$demo->appendMetaTag('description', 'A demo page');
$demo->appendCssUrl('/css/site.css');
$demo->appendJsUrl('/js/site.js', ['defer' => 'defer']);
$content->setText('rendered', $demo->toString());

renderPage('Head assets', $content, ['assets.php', 'templates/assets.html']);
