<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Builder;
use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/builder.html');
$lib = Builder::load(__DIR__ . '/templates/components.html');

// A plain component
$card = $lib->get('card');
$card->text('title', 'A card')->text('body', 'Fetched with $lib->get(\'card\').');
$content->append('cards', $card);

// A more specific component, overriding the card's look
$highlight = $lib->get('card.highlight');
$highlight->text('title', 'card.highlight')->text('body', 'Its own markup, defined in the same library file.');
$content->append('cards', $highlight);

// A name that doesn't exist falls back to its nearest parent
$special = $lib->get('card.special');
$special->text('title', 'card.special')->text('body', sprintf('No such component, so it fell back to `%s`.', $lib->match('card.special')));
$content->append('cards', $special);

// data-opt-* settings on a component, for PHP to read
$alert = $lib->get('alert');
$alert->text('message', 'The library sets this alert\'s level with data-opt-level.')
    ->addClass('message', 'alert-' . ($lib->options('alert')['level'] ?? 'info'));
$content->html('alert', $alert);

// Components can hold repeats too
$list = $lib->get('list');
$list->each('item', ['Parsed once', 'Cloned per get()', 'Shareable across requests'], fn($row, $text) => $row->text('item', $text));
$content->html('list', $list);

renderPage('Builder', $content, ['builder.php', 'templates/builder.html', 'templates/components.html']);
