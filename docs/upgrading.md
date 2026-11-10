# Upgrading from 8.x

!!! danger "Not a drop-in upgrade"
    v9 is a rewrite with breaking changes. Projects on 8.x keep working as they are. Moving one to v9
    means refactoring every template call, so plan it as a project of its own.

## Staying on 8.x

8.x is maintained on the [`8.1` branch](https://github.com/tropotek/tk-domtemplate/tree/8.1).
Keep your constraint on 8.x and Composer will never pick up v9:

```json
{
  "require": {
    "ttek/tk-domtemplate": "^8.1"
  }
}
```

Older lines are still available on their own branches: `8.0`, `7.0`, `3.2` and `3.0`.

The 8.x documentation is still at <https://domtemplate.tropotek.com/> (no longer updated).

## What changes in v9

| 8.x | 9.x |
| --- | --- |
| Namespace `Dom\` | `Tk\DomTemplate\` (PHP 8.4 owns `Dom\`) |
| PHP 8.3+ | PHP 8.5+ |
| `var`, `choice` and `repeat` attributes | one `var` attribute: `name`, `name?`, `name[]` |
| `setText()`, `setAttr()`, `addCss()` … | handles (`var('x')->text()->attr()`) and shortcuts (`text()`, `attr()`, `addClass()`) |
| `setVisible()` | `show()` / `hide()`; hidden nodes are removed |
| `getRepeat()` + `appendRepeat()` | `repeat()` + `Row::insert()` / `insertInto()`, or `each()` |
| `insertTemplate()`, `appendTemplate()` | `html()`, `append()` with a template; rendered lazily with the page |
| `appendJs()` / `appendHeadJs()`, `appendCss()` / `appendHeadCss()` | `js()` / `css()` with `Place` |
| `appendJsUrl()`, `appendCssUrl()` | `jsUrl()`, `cssUrl()` (once per page) |
| `appendMetaTag()`, `setTitleText()` | `meta()`, `title()` |
| `toString()` | `render()` or `(string)$template` |
| `reset()` | removed: load the template again |
| `getDocument()`, `getElementById()`, `getRootElement()` | removed: no direct DOM access; use a [modifier](modifiers.md) for low-level changes |
| changes after rendering were ignored | they throw a `LogicException` |
| `Builder` with `id="tpl-…"` sections | `Builder` with `data-tpl` names, layering and dotted fallback |
| `Modifier` walking every node | `Modifier` list of selector-based `ModifierInterface` passes |
| `Parser`, `FormParser`, `Form\*` | removed |
| `JsLast`, `Scss`, `UrlPath`, `PageBytes` modifiers | removed |
| `__makeTemplate()` on renderers | `makeTemplate()` |
| silent failures | throw in development, log in production ([Engine](engine.md)) |

## Moving a project to v9

v9 is meant for new projects. Moving an existing one means:

1. Requiring PHP 8.5 and `"ttek/tk-domtemplate": "9.0.x-dev"`.
2. Rewriting template markup: `choice="x"` becomes `var="x?"`, `repeat="x"` becomes `var="x[]"`.
3. Rewriting every render method against the new API.
4. Porting any custom modifiers to `ModifierInterface`.

Plan it as a project of its own.
