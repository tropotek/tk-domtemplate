# Engine

Site-wide settings live in an optional `Engine`, configured once:

```php
use Tk\DomTemplate\Engine;

$engine = new Engine(
    strict: $isDev,           // throw on template mistakes (true) or log them (false)
    logger: $psrLogger,       // PSR-3 logger for production; error_log() when null
    nonce: fn() => $request->cspNonce(), // CSP nonce, read at each render
    modifiers: $modifiers,    // final-pass modifiers (a ModifierList)
);

$page = $engine->file(__DIR__ . '/layout.html');
$part = $engine->load('<p var="msg"></p>');
```

- The engine is optional. Without one, `Template::load()` and `Template::file()` are strict, with no logger, nonce or modifiers.
- A template inserted into another uses that template's engine, unless it has its own, at any depth: a grandchild inserted before its parent joined the page still follows the page's engine. Rows always use their template's engine.
- The engine never changes after construction, so one instance can be shared across requests in a worker runtime such as FrankenPHP.

## CSP nonce

The nonce is added to the `<script>`, `<style>` and `<link rel="stylesheet">` elements written in template files and strings you load, and to those created by `js()`, `css()`, `jsUrl()` and `cssUrl()`. Scripts and styles inside HTML strings passed to `html()`, `append()`, `prepend()` or `replace()` don't get it, so injected markup is still blocked by the CSP. Add scripts with `js()` instead.

A nonce is per request, so pass a closure when the engine is shared (or a [Builder](builder.md) holds it): it is called at each render. A plain string suits an engine made per request. A closure returning `null` adds no nonce.

A nonce can't cover event-handler attributes (`onclick`), and `attr('href', $url)` doesn't block `javascript:` URLs; validate URLs from users yourself.

## Strict and lenient

| | `strict: true` (default) | `strict: false` |
|---|---|---|
| Unknown name, wrong kind, bad markup | throws `TemplateException` | logs a warning; the call does nothing |
| Change after render, circular insert, inserting a rendered template | throws `LogicException` | throws `LogicException` |

In lenient mode a failed lookup never shows hidden content: an optional node that couldn't be resolved stays removed. Var markers inside `<template>` content are logged and left in the output as written.
