# Assets

Any template can ask for scripts, styles, meta tags and a title. They are placed when the page renders.

```php
use Tk\DomTemplate\Place;

$t->js('initTable();');                        // inline: at the end of this template
$t->js('track();', Place::Head);               // end of the page <head>
$t->js('boot();', Place::End);                 // end of the page <body>
$t->css('.card { gap: 1rem; }');               // inline, or Place::Head
$t->jsUrl('/js/app.js', ['defer' => true]);    // <head>, once per page
$t->cssUrl('/css/app.css');                    // <head>, once per page
$t->meta('description', 'Page description');  // <head>; the last call wins
$t->title('Page title');                       // replaces or creates <title>
$t->js('setupSelects();', key: 'select-init'); // written once per page
```

## Where things go

- **Inline** (the default for `js()` and `css()`) writes the asset at the end of the template that added it, so it travels with that component into pages and HTMX fragments. A row's inline asset goes at the end of the row.
- **Head** and **End** go to the page's `<head>` or the end of its `<body>`, after the layout's own scripts.
- URLs, meta and title always go to `<head>`.
- A fragment has no `<head>` or `<body>`, so everything is written inline at its end and the title is dropped. That is how an HTMX response carries its assets.
- Head assets follow call order across templates; `meta()` and `title()` keep the last value set.

## De-duplication

- `jsUrl()` and `cssUrl()` add each URL once per page, and skip URLs the page already links with `<script src>` or `<link rel="stylesheet">` (a `rel="preload"` link doesn't count).
- URLs are compared as written: `css/a.css` and `/css/a.css` count as two URLs.
- Attribute names passed to `js()`, `css()`, `jsUrl()` and `cssUrl()` are checked at the call.
- Code with a `key` is written once per page, however many components or rows add it.
- Hidden components, and rows that are hidden or removed, contribute no assets.

## Safety

- In JavaScript, `<!--`, `<script` and `</script` are written with `\u003C` for `<` (the same value in strings, regexes and JSON), and `</style` in CSS becomes `<\/style`, so code can't end its element early.
- With a CSP nonce set on the [engine](engine.md), assets and the scripts and styles written in templates get it; scripts in inserted HTML strings don't.
