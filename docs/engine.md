# Engine

Site-wide settings live in an optional `Engine`, configured once:

```php
use Tk\DomTemplate\Engine;

$engine = new Engine(
    strict: $isDev,           // throw on template mistakes (true) or log them (false)
    logger: $psrLogger,       // PSR-3 logger for production; error_log() when null
    nonce: $cspNonce,         // added to every inline <script> and <style>
    modifier: $modifiers,     // final-pass modifiers
);

$page = $engine->file(__DIR__ . '/layout.html');
$part = $engine->load('<p var="msg"></p>');
```

- The engine is optional. Without one, `Template::load()` and `Template::file()` are strict, with no logger, nonce or modifiers.
- A template inserted into a page uses the page's engine from the moment it is inserted, unless it has its own. Rows always use their template's engine.
- The engine never changes after construction, so one instance can be shared across requests in a worker runtime such as FrankenPHP.

## Strict and lenient

| | `strict: true` (default) | `strict: false` |
|---|---|---|
| Unknown name, wrong kind, bad markup | throws `TemplateException` | logs a warning; the call does nothing |
| Change after render, circular insert, inserting a rendered template | throws `LogicException` | throws `LogicException` |

In lenient mode a failed lookup never shows hidden content: an optional node that couldn't be resolved stays removed. Var markers inside `<template>` content are logged and left in the output as written.
