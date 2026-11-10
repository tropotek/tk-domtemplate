# Templates

A template is a full HTML page or a fragment. PHP finds the nodes marked with a `var` attribute and changes them.

## Loading and rendering

```php
use Tk\DomTemplate\Template;

$t = Template::load('<h1 var="title">Default</h1>');   // from a string
$t = Template::file(__DIR__ . '/page.html');           // from a file (the path appears in error messages)

echo $t->render();      // or (string)$t
```

- Markup starting with `<!doctype` or `<html>` is a **full page**; anything else is a **fragment**.
- Fragments may have several top-level nodes, including table rows (`<tr>…</tr><tr>…</tr>`) or `<option>` lists.
- `render()` builds the template once. After that it is **locked**: any change throws a `LogicException`. Calling `render()` again returns the same string.

## Marking nodes

One attribute, `var`, names a node. Its suffix sets the kind:

| Markup | Kind | Meaning |
|---|---|---|
| `var="title"` | var | A node PHP changes. Visible by default. |
| `var="admin?"` | optional | Removed at render unless PHP shows it. Its content can still be set. |
| `var="users[]"` | repeat | A sample row, cloned once per item. See [Repeats](repeats.md). |

- PHP always uses the bare name: `title`, `admin`, `users`.
- Several nodes may share a name; a call changes all of them.
- Names may contain letters, digits, `-`, `_` and `.`.
- The `var` attribute is removed from the output. HTML's own `hidden` attribute is left alone.

## Handles and shortcuts

`$t->var('name')` returns a handle on every node with that name. Its methods chain:

```php
$t->var('link')
    ->text('Docs')
    ->attr(['href' => '/docs', 'target' => '_blank'])
    ->addClass('active');
```

For a single change, use the shortcut on the template:

| Handle | Shortcut | Does |
|---|---|---|
| `text($s)` | `$t->text('x', $s)` | set the text, escaped |
| `html($h)` | `$t->html('x', $h)` | replace the contents with HTML or a template |
| `append($h)` / `prepend($h)` | `$t->append('x', $h)` | add HTML or a template at the end / start |
| `replaceWith($h)` | `$t->replace('x', $h)` | replace the node itself |
| `attr($name, $value)` / `attr([...])` | `$t->attr('x', 'href', $u)` | set attributes |
| `removeAttr($name)` | | remove an attribute |
| `addClass($c)` / `removeClass($c)` | `$t->addClass('x', 'active')` | add or remove whole class names |
| `show($bool)` / `hide()` | `$t->show('x', $cond)` / `$t->hide('x')` | keep or remove at render |
| `getText()`, `getAttr($name)`, `count()` | | read |

- `text()` is the only escaped path. Everything else inserts HTML.
- HTML is parsed in the context of the target node, so `html('tbody', '<tr>…</tr>')` keeps the rows.
- `attr()` values: `true` sets an empty attribute (`download=""`), `false` or `null` removes it. Alpine names such as `@click` and `:class` work.
- `removeClass('btn')` removes only the `btn` class, not `btn-primary`.
- `$t->has('x')` checks whether a name exists, for optional markup in components.
- After `replace('x', …)`, the name refers to the replacement, so `show()`, `hide()` and attribute changes still apply.

## Visibility

```html
<a var="edit?" href="/edit">Edit</a>
<p var="price">0</p>
```
```php
$t->show('edit', $user->canEdit());   // optional: removed unless shown
$t->hide('price');                     // plain: removed when hidden
```

Hidden nodes are **removed** from the output, not hidden with CSS. Optional nodes are mostly permission checks, so their content never reaches the browser.

## Errors

| Mistake | Result |
|---|---|
| Unknown name | ``No var `titel` in page.html. Did you mean `title`?`` |
| Wrong kind (`var()` on a repeat) | ``"`users` is a repeat in page.html, use repeat()"`` |
| A name used as two kinds, or malformed | Reported when the template loads |
| A change after render | `LogicException`, always |

By default these throw a `TemplateException`. An [Engine](engine.md) with `strict: false` logs them instead and carries on, which suits production.

## HTML5 output

Templates are parsed and written by PHP's HTML5 parser, `Dom\HTMLDocument`, so a few things come out normalised:

- A bare attribute is written with an empty value: `hidden` becomes `hidden=""`.
- Single-quoted attribute values are written with double quotes, escaping as needed.
- Attribute names are lowercased.
- Broken HTML never throws; it is repaired the way browsers repair it.

DomTemplate reserves the `<tk-slot>` element and the `data-tk-key` attribute for its own use.
