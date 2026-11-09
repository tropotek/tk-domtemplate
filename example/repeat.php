<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Template;

$content = Template::loadFile(__DIR__ . '/templates/repeat.html');

$users = [
    ['name' => 'Ada Lovelace',  'email' => 'ada@example.com',   'active' => true],
    ['name' => 'Alan Turing',   'email' => 'alan@example.com',  'active' => false],
    ['name' => 'Grace Hopper',  'email' => 'grace@example.com', 'active' => true],
];

// A repeat is cloned once per row and appended after the previous row
foreach ($users as $user) {
    $row = $content->getRepeat('user');
    $row->setText('name', $user['name']);
    $row->setText('email', $user['email']);
    $row->setAttr('email', 'href', 'mailto:' . $user['email']);
    // Choices inside a repeat apply to that row only
    $row->setVisible($user['active'] ? 'active' : 'inactive');
    $row->appendRepeat();
}
$content->setVisible('hasUsers', count($users) > 0);

// The same table with no rows falls back to the empty-state choice
$content->setVisible('noTasks');

renderPage('Choices & repeats', $content, ['repeat.php', 'templates/repeat.html']);
