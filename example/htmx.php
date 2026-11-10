<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/htmx.html');

renderPage('HTMX + Alpine', $content, ['htmx.php', 'templates/htmx.html', 'fragment.php']);
