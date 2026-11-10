# Alpine and HTMX

DomTemplate parses with PHP's HTML5 parser, so the markup Alpine.js and HTMX rely on passes through untouched:

- Alpine's `x-data`, `x-show`, `@click`, `:class` and `x-on:…` attributes
- HTMX's `hx-get`, `hx-target`, `hx-swap` and other `hx-*` attributes
- `<template x-for>` and `<template x-if>` content, also inside repeat rows and cloned templates

`attr()` can set Alpine's shorthand names: `$t->attr('button', '@click', 'open = !open')`.

## HTMX fragments

An HTMX request wants a piece of HTML, not a page. Render a fragment template and return it:

```php
$fragment = Template::file(__DIR__ . '/user-row.html');
$fragment->text('name', $user->name);
$fragment->js('initRow();');      // inline: arrives with the fragment
$fragment->cssUrl('/css/row.css'); // no <head> in a fragment, so it is written inline
echo $fragment->render();
```

With no `<head>` or `<body>`, every asset is written inline at the end of the fragment and the title is dropped, so the response carries everything it needs. Modifiers on the engine run on fragments too.

## Limits

Var markers inside `<template>` content are not processed, because PHP's DOM can't reach that content. They are reported as a mistake. Keep server-side `var` markers outside Alpine templates.
