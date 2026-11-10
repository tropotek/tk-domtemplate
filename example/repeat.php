<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$content = Template::file(__DIR__ . '/templates/repeat.html');

$users = [
    ['name' => 'Ada Lovelace', 'email' => 'ada@example.com',   'active' => true,  'tags' => ['maths', 'engines']],
    ['name' => 'Alan Turing',  'email' => 'alan@example.com',  'active' => false, 'tags' => ['computing']],
    ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'active' => true,  'tags' => []],
];

// each() clones the sample row per item; vars inside a row belong to that row
$content->each('user', $users, function (Row $row, array $user) {
    $row->text('name', $user['name']);
    $row->var('email')->text($user['email'])->attr('href', 'mailto:' . $user['email']);
    $row->show($user['active'] ? 'active' : 'inactive');

    // A repeat inside a repeat: one badge per tag
    $row->each('tag', $user['tags'], fn(Row $tag, string $t) => $tag->text('tag', $t));
    $row->show('noTags', !$user['tags']);
});
$content->show('hasUsers', count($users) > 0);

// The same pattern with no rows falls back to an optional empty-state node
$content->show('noTasks');

renderPage('Repeats', $content, ['repeat.php', 'templates/repeat.html']);
