<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Place;
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/assets.html');

// Inline (the default): written at the end of this template, so it travels with it
$content->js("document.getElementById('inlineTarget').textContent = 'Set by an inline js() asset.';");

// Head and End: moved to the page's <head> or the end of its <body>
$content->css('.demo-box { border-left: .25rem solid var(--bs-primary); }', Place::Head);
$content->js("document.getElementById('endTarget').textContent = 'Set by a Place::End script, after the layout scripts.';", Place::End);

// URLs go to <head> once per page, and are skipped when the page already links them
$content->cssUrl('css/assets-demo.css');
$content->cssUrl('css/example.css');  // the layout already links this one, so nothing is added
$content->meta('description', 'DomTemplate assets example');

// A keyed script added by every row is written only once
$content->each('row', ['One', 'Two', 'Three'], function (Row $row, string $label) {
    $row->text('row', $label);
    $row->js("window.keyedRuns = (window.keyedRuns || 0) + 1; document.getElementById('keyedTarget').textContent = 'The keyed script ran ' + window.keyedRuns + ' time(s) for 3 rows.';", key: 'rowSetup');
});

// The same helpers on a standalone fragment: with no <head> or <body>, everything stays inline
$fragment = Template::load('<p>A fragment</p>')
    ->cssUrl('/css/widget.css')
    ->js('initWidget();', Place::Head)
    ->title('Ignored in a fragment');
$content->text('fragment', $fragment->render());

renderPage('Assets', $content, ['assets.php', 'templates/assets.html']);
