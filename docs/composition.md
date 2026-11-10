# Inserting templates

`html()`, `append()`, `prepend()` and `replace()` accept a template as well as an HTML string.

```php
$page = Template::file('layout.html');
$table = Template::file('users-table.html');

$page->html('content', $table);
$table->text('caption', 'Users');   // still works: inserts are lazy
echo $page->render();
```

- An inserted template is kept as a reference and rendered when the page renders, so it can still be changed after it was inserted.
- Calls keep their order, even when string and template inserts are mixed on one node.
- A template inserted in several places is built once and copied into each place.
- Inserting a full page inserts its `<body>` contents.
- A template inside hidden or overwritten content is never built.
- Inserting a template into itself, directly or through other templates, throws a `LogicException` before anything is built, so every template involved is unchanged.
- Insert templates before rendering them. A template rendered on its own has its assets and modifiers in its output already, so inserting it throws a `LogicException`, and so does rendering a page whose child was rendered on its own after it was inserted. A child built as part of one page can be inserted into another.
- Rows can't be inserted as templates; place them with `insert()` or `insertInto()`.
- After the page renders, the inserted templates are locked too.
- A child without its own [engine](engine.md) uses the engine of the template it was inserted into, looked up when needed, so templates nested several levels deep follow the page's engine.
- After `replace('x', $template)`, attributes and classes set on `x` carry over to the template's elements, but `text()` and `html()` on `x` are reported: change the inserted template instead.
- A template can't be inserted into a `<template>` element; insert `$child->render()` as a string.

## Cloning

`clone $template` gives an independent copy of a template that hasn't rendered yet, including its visibility settings, rows and pending inserts. Inserted child templates are shared, not copied. Cloning a rendered template, or a row, throws.
