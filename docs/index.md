# PHP DomTemplate

DomTemplate renders HTML templates from PHP without a template language. Layout and design stay in plain
HTML that designers can open and edit. Render logic stays in PHP, which finds marked nodes and fills them in.

```html
<h1 var="title">Default title</h1>
<p choice="welcome">Welcome back!</p>
<ul>
  <li repeat="item" var="name">Item</li>
</ul>
```

```php
use Tk\DomTemplate\Template;

$template = Template::loadFile('page.html');
$template->setText('title', 'My page');
$template->setVisible('welcome', $user->isReturning());

foreach ($items as $item) {
    $row = $template->getRepeat('item');
    $row->setText('name', $item->name);
    $row->appendRepeat();
}

echo $template->toString();
```

!!! warning "Version 9 is in development"
    These docs follow the `main` branch, which is the start of v9. It is not backward compatible with 8.x.
    Existing projects should stay on `^8.1`. See [Upgrading from 8.x](upgrading.md).

## Features

- **`var`** nodes: set their text, HTML, attributes and CSS classes.
- **`choice`** nodes: removed from the output unless you make them visible.
- **`repeat`** nodes: clone once per row, for lists and tables.
- **Head assets:** add meta tags, CSS and JavaScript from any template; they move up to the page `<head>`.
- **Composition:** insert templates, DOM documents or HTML strings into each other.
- **Forms:** set field values, options and selections by field name.

## Next steps

- [Getting started](getting-started.md): install and render your first template.
- [Example site](examples.md): run a Bootstrap demo of every feature locally.
- [Templates guide](domTemplate.md): the full `Template` API.
