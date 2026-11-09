# Getting started

## Requirements

- PHP 8.4 or newer
- The `dom`, `libxml`, `mbstring` and `tidy` extensions

## Install

```bash
composer require ttek/tk-domtemplate
```

!!! note
    While v9 is unreleased, require the development branch with `"ttek/tk-domtemplate": "9.0.x-dev"`.
    For existing 8.x projects, use `"^8.1"`.

## Your first template

Write the page as plain HTML. Mark the nodes PHP will change with `var`, `choice` or `repeat` attributes:

```html title="templates/hello.html"
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Hello</title>
</head>
<body>
  <h1 var="heading">Default heading</h1>
  <p choice="loggedIn">You are logged in.</p>
  <ul>
    <li repeat="fruit" var="fruitName">Fruit</li>
  </ul>
</body>
</html>
```

Then load it and fill it in:

```php title="hello.php"
<?php
require __DIR__ . '/vendor/autoload.php';

use Tk\DomTemplate\Template;

$template = Template::loadFile(__DIR__ . '/templates/hello.html');

$template->setTitleText('Hello World');
$template->setText('heading', 'Hello World');
$template->setVisible('loggedIn');

foreach (['Apple', 'Banana', 'Cherry'] as $fruit) {
    $row = $template->getRepeat('fruit');
    $row->setText('fruitName', $fruit);
    $row->appendRepeat();
}

$template->appendCssUrl('/css/site.css');

echo $template->toString();
```

The output has the `var`, `choice` and `repeat` attributes removed, the choice shown, one `<li>` per fruit,
and the stylesheet link added to `<head>`.

## How it works

1. **Load:** `Template::load()` or `Template::loadFile()` parses the HTML and indexes every marked node.
2. **Change:** call methods such as `setText()`, `setAttr()` or `setVisible()` on the template.
3. **Render:** `toString()` (or `getDocument()`) parses the template once. Choices that weren't made
   visible are removed, and head assets are written to `<head>`.

!!! warning
    A template is locked after it renders. Further changes are ignored, so make all changes first.

## Next steps

- [Templates guide](domTemplate.md) for the full API.
- [Example site](examples.md) to see each feature running.
