# Getting Started with Dom\Template

This guide shows how to load, modify, and render HTML using the Dom\Template class. It assumes you’re familiar with PHP and the DOM extension.

## Basic Workflow

1) Load a template

   - From a string:
```php
<?php
use Dom\Template;

$template = Template::load('<!doctype html><html><head><title></title></head><body><h1 var="heading"></h1></body></html>');
```
   - From a file:
```php
<?php
use Dom\Template;

$template = Template::loadFile(__DIR__ . '/template.html');
```

2) Set content
```php
<?php
$template
    ->setTitleText('Welcome')
    ->setText('heading', 'Hello World');
```

3) Render
```php
<?php
echo $template->toString(); // or: $doc = $template->getDocument(); $html = $template->toString();
```


## Marking Up Your HTML

- Variables (var): mark elements you’ll programmatically modify
```html
<h1 var="heading"></h1>
<div var="content"></div>
```

- Choices (choice): mark elements you want hidden initially, but may be shown later
```html
<p choice="promo">Limited time offer!</p>
```

- Repeat (repeat): mark a section intended for repetition (see [Repeating Regions](#repeating-regions)
```html
<ul>
  <li repeat="items"><span var="itemName"></span></li>
</ul>
```


- Regular id attributes are indexed for quick access by `getElementById()`

## Setting Text and HTML

- Replace text:
```php
<?php
$template->setText('content', 'This is the body text.');
```

- Append/prepend text:
```php
<?php
$template->appendText('content', ' More…');
$template->prependText('content', 'Intro: ');
```

- Replace inner HTML:
```php
<?php
$template->setHtml('content', '<p><strong>Formatted</strong> content.</p>');
```

- Append/prepend inner HTML:
```php
<?php
$template->appendHtml('content', '<p>Another paragraph.</p>');
$template->prependHtml('content', '<p>Lead paragraph.</p>');
```

- Replace the entire var node (tag) with new HTML:
```php
<?php
$template->replaceHtml('content', '<section class="new">New block</section>');
```

## Attributes and CSS Classes

- Set attributes:
```php
<?php
$template->setAttr('content', ['data-role' => 'main', 'aria-live' => 'polite']);
```


- Get/remove attributes:
```php
<?php
$current = $template->getAttr('content', 'data-role');
$template->removeAttr('content', 'aria-live');
```


- Add/remove classes:
```php
<?php
$template->addCss('content', 'container highlight'); // adds distinct classes
$template->removeCss('content', 'highlight');
```


## Choices: Show/Hide Nodes

- Toggle visibility of choice or var nodes:
```php
<?php
$template->setVisible('promo', true);  // show
$template->setVisible('promo', false); // hide (removed on parse)
```


## Repeating Regions

- Define a repeat in HTML `repeat="items"` and a child var inside it `var="itemName"`.
- At runtime, clone the repeat, set values, and insert. The repeat template node is removed on parse.

Example pattern:
```php
<?php
$template = Template::load('<ul><li repeat="item"><span var="itemName"></span></li></ul>');

foreach (['One', 'Two', 'Three'] as $name) {
  $repeat = $template->getRepeat('item');
  $repeat->setText('itemName', $name);
  $repeat->appendRepeat();
}
```
!!! tip
    You can also target specific containers (var) using `appendRepeat('varName')` to append to a specific var node.
    By default, a repeat is appended to its parent node.
    

## Head Tag Management

- Add meta:
```php
<?php
$template->appendMetaTag('viewport', 'width=device-width, initial-scale=1');
```


- Add external CSS and JS:
```php
<?php
$template
    ->appendCssUrl('/assets/app.css')
    ->appendJsUrl('/assets/app.js', ['defer' => 'defer']);
```


- Add inline CSS/JS to head:
```php
<?php
$template
    ->appendHeadCss('body { margin: 0; }')
    ->appendHeadJs('console.log("ready");');
```


- Add inline CSS/JS to the body (or root node when no body exists):
```php
<?php
$template->appendCss('.box{padding:1rem;}');
$template->appendJs('alert("Hello");');
```

!!! Note
    Head/body insertions only work before parsing.


## Composing Templates

- Append a whole template into another template’s body, hady for inserting dialogue markup:
```php
<?php
$child = Template::load('<div var="content"><p>Child block</p></div>');
$template->appendBodyTemplate($child);
```


- Insert/append/prepend a parsed template into a var:
```php
<?php
$template->appendTemplate('content', $child);
```

Headers (meta/link/script/style added via appendHeadElement/appendCssUrl/etc.) are merged when you combine templates.

## Parsing and Rendering

- calling `getDocument()` or `toString()` parses and finalizes the document (removes hidden/choice nodes, resolves repeats, inserts head elements). After parsing, mutation methods are no-ops.
```php
<?php
echo $template->toString();
```

- Reset to the original state if you want to reuse the template again with different data:
```php
<?php
$template->reset(); // restores original DOM and state
```

## IDs and Direct DOM Access

- Access by id:
```php
<?php
$el = $template->getElementById('main');
```

- Direct DOM operations:
```php
<?php
$doc = $template->getDocument(false); // don’t parse yet
$root = $template->getRootElement();
```

## Encoding and Doctype

- The template tracks original encoding and doctype.
- `isHtml5()` checks for <!doctype html>.
```php
<?php
if ($template->isHtml5()) {
    // e.g., avoid type="text/javascript"
}
```


## Tracing (Debugging)

- Enable tracing to tag injected JS/CSS with the attribute `data-dt-trace` showing callback location:
```php
<?php
Dom\Template::$ENABLE_TRACER = true;
$template->appendJsUrl('/app.js'); // will include data-dt-trace
```


## Tips and Gotchas

- Perform all modifications before `getDocument()` or `toString()`.
- Missing vars/choices/repeats simply no-op.
- `replaceHtml()` replaces the var element itself; `setHtml()` replaces its inner contents.
- For safety, provide valid HTML fragments when inserting HTML.
- Use `reset()` to reuse the same Template instance.

## Minimal Example

```php
<?php
use Dom\Template;

$html = '<!doctype html><html><head><title var="pageTitle"></title></head><body><h1 var="heading"></h1><div var="content"></div></body></html>';

$t = Template::load($html);
$t->setTitleText('Demo')
  ->setText('heading', 'Welcome')
  ->setHtml('content', '<p>This is a <strong>demo</strong>.</p>')
  ->appendCssUrl('/assets/site.css')
  ->appendJsUrl('/assets/site.js', ['defer' => 'defer']);

echo $t->toString();
```


That's it, start by marking var/choice/repeat in your HTML, then use the API to fill, toggle, repeat, and render.


