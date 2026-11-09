<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::loadFile(__DIR__ . '/templates/index.html');

// Build the example cards from the page list (skipping Home itself)
foreach (array_slice(PAGES, 1, null, true) as $file => $label) {
    $card = $content->getRepeat('card');
    $card->setText('cardTitle', $label);
    $card->setAttr('cardLink', 'href', $file);
    $card->appendRepeat();
}

renderPage('PHP DomTemplate', $content, ['index.php', 'templates/index.html', '_init.php', 'templates/layout.html']);
