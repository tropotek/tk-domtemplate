# PHP DomTemplate

DomTemplate renders HTML templates from PHP without a template language. Layout and design stay in plain
HTML that designers can open and edit. Render logic stays in PHP, which finds named nodes and fills them in.

```html
<h1 var="title">Default title</h1>
<p var="welcome?">Welcome back!</p>
<ul>
  <li var="item[]">Item</li>
</ul>
```

```php
use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$t = Template::file('page.html');
$t->text('title', 'My page');
$t->show('welcome', $user->isReturning());
$t->each('item', $items, fn(Row $row, Item $item) => $row->text('item', $item->name));

echo $t->render();
```

!!! warning "Version 9"
    These docs cover v9, which is not backward compatible with 8.x.
    Existing projects should stay on `^8.1`. See [Upgrading from 8.x](upgrading.md).

## Features

- **One attribute:** `var="name"` for a node PHP changes, `var="name?"` for one that is removed unless shown, `var="name[]"` for a repeating row.
- **Chainable handles** (`$t->var('link')->text(…)->attr(…)`) and one-line shortcuts (`$t->text('title', …)`).
- **Lazy composition:** insert templates into templates; they render with the page.
- **Assets:** scripts, styles, meta and title from any template, placed in the page `<head>`, at the end of `<body>` or inline.
- **Alpine.js and HTMX** markup passes through untouched, and fragments carry their own assets.
- **Modifiers:** fast, selector-based final passes over the output.
- **Builder:** reusable components from plain HTML library files.
- **Safe failures:** mistakes throw in development and are logged in production; hidden content never reaches the browser.

## Next steps

- [Getting started](getting-started.md): install and render your first template.
- [Example site](examples.md): run a Bootstrap demo of every feature locally.
- [Templates](templates.md): the full API.
