<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/index.html');

// One card per example page, skipping Home itself
$content->each('card', array_slice(PAGES, 1, null, true), function (Row $card, string $label, string $file) {
    $card->text('title', $label)->attr('card', 'href', $file);
});

renderPage('PHP DomTemplate', $content->render(), ['index.php', 'templates/index.html', '_init.php', 'templates/layout.html']);
