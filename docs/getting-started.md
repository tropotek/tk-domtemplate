# Getting started

## Requirements

- PHP 8.5 or newer
- The `dom` extension

## Install

```bash
composer require ttek/tk-domtemplate
```

!!! note
    While v9 is unreleased, require the development branch with `"ttek/tk-domtemplate": "9.0.x-dev"`.
    For existing 8.x projects, use `"^8.1"`.

## Your first template

Write the page as plain HTML and name the nodes PHP will change:

```html title="templates/hello.html"
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Hello</title>
</head>
<body>
  <h1 var="heading">Default heading</h1>
  <p var="loggedIn?">You are logged in.</p>
  <ul>
    <li var="fruit[]">Fruit</li>
  </ul>
</body>
</html>
```

Then load it and fill it in:

```php title="hello.php"
<?php
require __DIR__ . '/vendor/autoload.php';

use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$t = Template::file(__DIR__ . '/templates/hello.html');

$t->title('Hello World');
$t->text('heading', 'Hello World');
$t->show('loggedIn');
$t->each('fruit', ['Apple', 'Banana', 'Cherry'], fn(Row $row, string $fruit) => $row->text('fruit', $fruit));
$t->cssUrl('/css/site.css');

echo $t->render();
```

The output has the `var` markers removed, the optional paragraph kept, one `<li>` per fruit, and the stylesheet linked in `<head>`.

## How it works

1. **Load:** `Template::load()` or `Template::file()` parses the HTML5 and indexes every named node.
2. **Change:** call `text()`, `attr()`, `show()`, `repeat()` and the rest.
3. **Render:** `render()` builds the page once. Hidden nodes and repeat samples are removed, inserted templates are rendered, and assets are placed.

!!! warning
    A template is locked after it renders: a later change throws a `LogicException`. Make every change first.

## Next steps

- [Templates](templates.md) for the full API, then [Repeats](repeats.md) and [Inserting templates](composition.md).
- [Engine](engine.md) for production settings: lenient errors, logging and a CSP nonce.
- [Example site](examples.md) to see each feature running.
