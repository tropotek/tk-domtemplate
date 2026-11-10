# PHP DomTemplate :boom: 

__Project:__ [ttek/tk-domtemplate](http://packagist.org/packages/ttek/tk-domtemplate)  
__Authors:__ Tropotek <http://www.tropotek.com/>  
__Documentation:__ <https://tropotek.github.io/tk-domtemplate/>  



> [!WARNING]
> `main` is **v9 (unreleased)**, a rewrite that is not backward compatible with 8.x.
> Existing projects should stay on `^8.1` (branch [`8.1`](https://github.com/tropotek/tk-domtemplate/tree/8.1)).
> See [Upgrading from 8.x](https://tropotek.github.io/tk-domtemplate/upgrading/).

DomTemplate renders HTML templates from PHP without a template language. Layout and design stay in plain
HTML that designers can open and edit. Render logic stays in PHP, which finds named nodes and fills them in.

- One `var` attribute: `var="name"`, `var="name?"` (removed unless shown), `var="name[]"` (a repeating row)
- Chainable handles and one-line shortcuts
- Templates inserted into templates, rendered lazily with the page
- Scripts, styles, meta and title from any template, placed in `<head>`, at the end of `<body>` or inline
- Alpine.js and HTMX markup passes through untouched; HTMX fragments carry their own assets
- Selector-based modifiers, a component `Builder`, and errors that throw in development and log in production


## Requirements

- PHP 8.5+
- `ext-dom`


## Installation

```bash
composer require ttek/tk-domtemplate:9.0.x-dev
```


## Basic usage

```html
<!-- page.html -->
<!doctype html>
<html lang="en">
<head><title>Hello</title></head>
<body>
  <h1 var="heading">Default heading</h1>
  <p var="admin?">Only admins see this.</p>
  <ul>
    <li var="item[]"><a var="link" href="#">Item</a></li>
  </ul>
</body>
</html>
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Tk\DomTemplate\Row;
use Tk\DomTemplate\Template;

$t = Template::file(__DIR__ . '/page.html');

$t->title('Hello World');
$t->text('heading', 'Hello World');
$t->show('admin', $user->isAdmin());
$t->each('item', $links, fn(Row $row, array $link) =>
    $row->var('link')->text($link['label'])->attr('href', $link['url'])
);
$t->cssUrl('/css/site.css');

echo $t->render();
```

Full documentation: <https://tropotek.github.io/tk-domtemplate/>


## Running the examples

The `example/` folder is a small Bootstrap site that shows each feature with its source.
It runs in a FrankenPHP container:

```bash
cp .env.example .env   # set APP_HOST, HTTP_APP_PORT, APP_UID/APP_GID
docker compose up -d --build
```

Then open `http://APP_HOST:HTTP_APP_PORT` (<http://127.0.0.1:8080> with the defaults).


## Development

With the container running:

```bash
docker compose exec app composer check   # phpstan + PHPUnit
bin/e2e                                  # browser tests of the example site (Playwright in Docker)
```

To release, add a `Ver X.Y.Z [unreleased]:` section to `changelog.md`, then run `bin/release X.Y.Z`.
It runs the checks, stamps the version and date, tags, and asks before pushing.


## Contributing

If you wish to contribute updates to the code or documentation, we are open to any requests.

Use the [GitHub discussions](https://github.com/tropotek/tk-domtemplate/discussions) area to chat about what you would like to see or contribute.

