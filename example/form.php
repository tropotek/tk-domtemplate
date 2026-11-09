<?php
require __DIR__ . '/_init.php';

use Tk\DomTemplate\Form\Select;
use Tk\DomTemplate\Parser;
use Tk\DomTemplate\Parser\FormParser;
use Tk\DomTemplate\Template;

// Register the form parser before loading the template it should read
$formParser = new FormParser();
Parser::instance()->registerParser($formParser);

$content = Template::loadFile(__DIR__ . '/templates/form.html');
$form = $formParser->getForm('contactForm');

/** @var Select $country */
$country = $form->getFormElement('country');
$country->appendOption('-- Select --', '');
foreach (['AU' => 'Australia', 'NZ' => 'New Zealand', 'UK' => 'United Kingdom', 'US' => 'United States'] as $code => $label) {
    $country->appendOption($label, $code);
}
$country->setValue('AU');

/** @var Select $colours */
$colours = $form->getFormElement('colours[]');
foreach (['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'] as $value => $label) {
    $colours->appendOption($label, $value);
}
$colours->setValue(['red', 'blue']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'      => trim((string)($_POST['name'] ?? '')),
        'email'     => trim((string)($_POST['email'] ?? '')),
        'country'   => (string)($_POST['country'] ?? ''),
        'colours[]' => array_map('strval', (array)($_POST['colours'] ?? [])),
        'comments'  => (string)($_POST['comments'] ?? ''),
    ];

    // Refill the form with what was submitted
    foreach ($data as $name => $value) {
        $form->getFormElement($name)->setValue($value);
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $content->addCss('emailField', 'is-invalid');
        $content->setText('emailError', 'Enter a valid email address.');
    } else {
        $content->setVisible('success');
        $content->setText('formData', print_r($data, true));
    }
}

// The layout doesn't need the form parser
Parser::instance()->clear();

renderPage('Forms', $content, ['form.php', 'templates/form.html']);
