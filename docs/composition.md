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
- Inserting a template into itself, directly or through other templates, throws a `LogicException`.
- After the page renders, the inserted templates are locked too.
- A child without its own [engine](engine.md) uses the page's engine from the moment it is inserted.

## Cloning

`clone $template` gives an independent copy of a template that hasn't rendered yet, including its visibility settings, rows and pending inserts. Inserted child templates are shared, not copied. Cloning a rendered template, or a row, throws.
