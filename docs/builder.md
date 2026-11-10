# Builder

A `Builder` serves named components from library files, for reusable, CSS-framework-specific markup such as form fields or table layouts.

A library file is a normal HTML page, so it can be opened in a browser to see every component at once:

```html
<div class="mb-3" data-tpl="form.input" data-opt-error-css="is-invalid">
  <label var="label"></label>
  <input class="form-control" var="element">
</div>
```

```php
use Tk\DomTemplate\Builder;

$lib = Builder::load([__DIR__ . '/bs5.html', __DIR__ . '/bs5-inline.html'], $engine);

$field = $lib->get('form.input.email');   // tries form.input.email, then form.input, then form
$opts = $lib->options('form.input');      // ['error-css' => 'is-invalid']
$lib->has('form.select');                 // true when get() would find something
$lib->exists('form.select');              // true only for that exact name
$lib->match('form.input.email');          // 'form.input': the name get() would use
```

- Every `get()` returns a fresh copy, labelled `file#name` in error messages. Names are trimmed.
- Each component is checked when the library loads, so markup mistakes are reported once, at `load()`, not on every `get()`. `load()` needs at least one file.
- Later files override earlier ones, one whole component at a time. A small override file can restyle one component without copying the rest.
- A dotted name falls back to its parents. Don't nest unrelated components under a container's name: with a `form` component, `get('form.hidden')` returns the `<form>`. Check with `exists()` or `match()`.
- `data-tpl` and `data-opt-*` are removed from what `get()` returns, nested components included.
- A name defined twice in one file is reported; the first one is kept.
- A missing component always throws `TemplateException`.
- Each file is parsed once and never changed, so one Builder can be shared across requests.
- Components that are table rows, cells or options must sit inside their proper parent (`<table>`, `<select>`) in a full-page library file; a fragment file would lose them.
