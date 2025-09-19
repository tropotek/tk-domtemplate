

# Dom\Template — Public API Guide

This guide shows how to use the public methods of the Template class to load, query, modify, and render HTML using a var/choice/repeat-driven DOM templating approach.

Contents:
- Quick start
- Loading and rendering
- Querying template structure
- Vars and choices
- Repeats
- Attributes and CSS
- Text and HTML manipulation
- Inserting other templates or documents
- Head/meta/JS/CSS helpers
- Forms
- Body templates
- Parse lifecycle hooks
- Utilities and configuration
- Common patterns and tips

---

## Quick start

```php
<?php
use Dom\Template;

// Load from a string or file
$t = Template::load('<div var="greeting"></div>');
// or: $t = Template::loadFile(__DIR__.'/page.html');

// Set content into a var
$t->setText('greeting', 'Hello World');

// Render to string
echo $t->toString();
```


---

## Loading and rendering

- Template::load(string $html, string $encoding = 'UTF-8'): Template  
  Create a template from an HTML string.

- Template::loadFile(string $filename, string $encoding = 'UTF-8'): Template  
  Create a template from an HTML file.

- Template::toString(bool $parse = true): string  
  Render the current state to an HTML string. When parse is true, final cleanups and insertions are performed.

- Template::getDocument(bool $parse = true): ?DOMDocument  
  Access the underlying DOMDocument. parse=true applies finalization steps first.

- Template::__toString(): string  
  Same as toString().

- Template::reset(): Template  
  Reset the template back to its original, unmodified state.

Examples:
```php
<?php
$t = Template::loadFile(__DIR__.'/template.html');

// Work with the DOM if needed
$doc = $t->getDocument(false);
$root = $t->getRootElement();

// Final HTML
$html = $t->toString();
```


---

## Querying template structure

- Template::getRootElement(): DOMElement  
  The document root element.

- Template::getElementById(string $id): ?DOMElement  
  Fast lookup for an element by “id”.

- Template::getHeadElement(): ?DOMElement, Template::getBodyElement(): ?DOMElement, Template::getTitleElement(): ?DOMElement  
  Access structural elements when present.

- Template::getTitleText(): string, Template::setTitleText(string $value): Template  
  Read/write the contents of the title tag.

- Template::getTemplateHtml(): string  
  Original HTML used to construct the template.

- Template::getTemplatePath(): string  
  Document URI (file path if loaded from file).

- Template::isHtml5(): bool  
  Whether source was detected as HTML5 (doctype).

- Template::getEncoding(): string  
  The document’s encoding.

Examples:
```php
<?php
if ($t->isHtml5()) {
    $t->setTitleText('My Page');
}
$main = $t->getElementById('main');
```


---

## Vars and choices

Vars mark elements to be targeted by name. Choices are conditional blocks that can be shown/hidden.

- Template::getVarList(null|string|DOMElement $var = null): array
    - null: returns all var mapping as [name => DOMElement[]]
    - string: returns all nodes for that var name
    - DOMElement: wraps it as a single-item list

- Template::getVar(string|DOMElement $var): ?DOMElement  
  First matching node for a var name. Use when you expect only one.

- Template::hasVar(string|DOMElement $var): bool  
  Whether a var exists.

- Template::removeVar(string|DOMElement $var): Template  
  Physically removes the nodes for that var from the DOM.

- Template::getChoiceList(): array  
  Map of available choices.

- Template::setVisible(string|DOMElement $choice, bool $b = true): Template  
  Show/hide a choice block (or any var node).

Examples:
```php
<?php
// Set a text value into an element marked var="status"
$t->setText('status', 'OK');

// Hide an optional block choice="advanced"
$t->setVisible('advanced', false);

// Remove a node entirely (use hide unless you must remove)
$t->removeVar('deprecated-note');
```


---

## Repeats

Repeats represent template fragments intended for duplication (like loops).

- Template::getRepeat(string $repeat): ?Repeat  
  Fetch a Repeat instance for a given name.

- Template::getRepeatList(): array  
  Get defined repeats.

Typical pattern:
```php
<?php
$items = [['name' => 'A'], ['name' => 'B']];
$rep = $t->getRepeat('item');
if ($rep) {
    foreach ($items as $row) {
        $rowTpl = $rep->create();         // clone the repeat block
        $rowTpl->setText('name', $row['name']);
        $rep->append($rowTpl);            // append the filled block
    }
}
```


Note: The Repeat class provides methods like create()/append() (check your project’s Repeat API). The repeat “placeholder” is removed on final parse.

---

## Attributes and CSS

- Template::setAttr(string|DOMElement $var, array|string $attr, null|string|int|float $value = null): Template
    - attr can be a string (single attribute) or an associative array of attributes.
- Template::getAttr(string|DOMElement $var, string $attr): string  
  Read attribute value.
- Template::removeAttr(string|DOMElement $var, string $attr): Template  
  Remove an attribute.

- Template::addCss(string|DOMElement $var, array|string $class): Template  
  Adds CSS class(es) without duplication.
- Template::removeCss(string|DOMElement $var, string $class): Template  
  Removes one class by name.

Examples:
```php
<?php
$t->setAttr('button-save', 'data-id', 42);
$t->setAttr('link', ['href' => '/go', 'target' => '_blank']);

$t->addCss('alert', ['alert', 'alert-warning']);
$t->removeCss('alert', 'hidden');
```


---

## Text and HTML manipulation

- Template::getText(string|DOMElement $var): string
- Template::setText(string|DOMElement $var, string|int|float $value): Template
- Template::appendText(string|DOMElement $var, string|int|float $value): Template
- Template::prependText(string|DOMElement $var, string|int|float $value): Template

- Template::getHtml(string|DOMElement $var): string
- Template::setHtml(string|DOMElement $var, string $html): Template
- Template::appendHtml(string|DOMElement $var, string $html): Template
- Template::prependHtml(string|DOMElement $var, string $html): Template
- Template::insertHtml(string|DOMElement $var, string $html): Template
- Template::replaceHtml(string|DOMElement $var, string $html, bool $preserveAttrs = true): Template

- Template::empty(string|DOMElement $var): Template  
  Remove all child nodes within the var element (keep the container).

Examples:
```php
<?php
// Text operations
$t->setText('title', 'Report');
$t->appendText('title', ' 2025');
$t->prependText('title', 'Daily ');

// HTML operations
$t->setHtml('content', '<p>Hello</p>');
$t->appendHtml('content', '<p>More...</p>');
$t->replaceHtml('hero', '<section class="hero">...</section>', preserveAttrs: true);
$t->empty('list'); // clears list contents
```


---

## Inserting other templates or documents

Insert entire Template or DOMDocument contents into a var element.

- Template::insertTemplate(string|DOMElement $var, Template $template): Template
- Template::appendTemplate(string|DOMElement $var, Template $template): Template
- Template::prependTemplate(string|DOMElement $var, Template $template): Template
- Template::replaceTemplate(string|DOMElement $var, Template $template, bool $preserveAttrs = true): Template

- Template::insertDocHtml(string|DOMElement $var, DOMDocument $doc): Template
- Template::appendDocHtml(string|DOMElement $var, DOMDocument $doc): Template
- Template::prependDocHtml(string|DOMElement $var, DOMDocument $doc): Template
- Template::replaceDocHtml(string|DOMElement $var, DOMDocument $doc, bool $preserveAttrs = true): Template

Examples:
```php
<?php
$child = Template::load('<div var="row"><span var="text"></span></div>');
$child->setText('text', 'Row content');

// Append a child template into a container
$t->appendTemplate('rows', $child);

// Replace an area entirely with another template
$t->replaceTemplate('sidebar', $child, preserveAttrs: false);
```


---

## Head/meta/JS/CSS helpers

These methods queue “header” elements that are inserted into <head> at parse time.

- Template::appendHeadElement(string $elementName, array $attributes, string $value = '', ?DOMElement $node = null): Template  
  Queue an arbitrary element for <head> (value goes into CDATA).

- Template::appendMetaTag(string $name, string $content, ?DOMElement $node = null): Template  
  Convenience for meta name/content.

- Template::appendCssUrl(string $styleUrl, array $attrs = [], ?DOMElement $node = null): Template  
  Queue a <link rel="stylesheet">.

- Template::appendCss(string $styles, array $attrs = []): Template  
  Queue an inline <style> block.

- Template::appendHeadCss(string $styles, array $attrs = [], ?DOMElement $node = null): Template  
  Same as appendCss but explicitly marked for head.

- Template::appendJsUrl(string $urlString, array $attrs = [], ?DOMElement $node = null): Template  
  Queue a <script src="..."> in head.

- Template::appendJs(string $js, array $attrs = []): Template  
  Queue an inline <script> in head.

- Template::appendHeadJs(string $js, array $attrs = [], ?DOMElement $node = null): Template  
  Inline script for head.

- Template::getHeaderList(): array  
  Inspect the queued headers.

Examples:
```php
<?php
$t->appendMetaTag('viewport', 'width=device-width, initial-scale=1');
$t->appendCssUrl('/assets/site.css', ['media' => 'all']);
$t->appendCss('body{opacity:.99}');
$t->appendJsUrl('/assets/app.js', ['defer' => 'defer']);
$t->appendJs('console.log("ready");');
```


Note: Meta elements are inserted before <title> when possible.

---

## Forms

- Template::getForm(string $id = ''): ?Form  
  Retrieve a Form wrapper by form id/name (or default), enabling form element binding.  
  You can also interact with form-related vars via the general var/attr APIs shown above.

Example:
```php
<?php
$form = $t->getForm('search');
if ($form) {
    // Combine with var/attr APIs for inputs
    $t->setAttr('q', 'value', 'keyword');
}
```


---

## Body templates

- Template::appendBodyTemplate(Template $template): Template  
  Queue a template to be appended into <body> on parse.

- Template::appendBodyTemplateList(array $arr): Template  
  Batch append body templates.

- Template::getBodyTemplateList(): array  
  Inspect queued body templates.

Example:
```php
<?php
$footer = Template::load('<footer var="footer">...</footer>');
$t->appendBodyTemplate($footer);
```


---

## Parse lifecycle hooks

- Template::setOnPreParse(callable $onPreParse): Template  
  Set a callback executed before final parse actions.

- Template::setOnPostParse(callable $onPostParse): Template  
  Set a callback executed after parse actions.

- Template::isParsed(): bool  
  Whether parse finalization has executed.

Example:
```php
<?php
$t->setOnPreParse(function(Template $tpl) {
    $tpl->setText('build', date('c'));
});
$t->setOnPostParse(function(Template $tpl) {
    // e.g., logging or metrics
});
$t->toString(); // triggers hooks
```


---

## Utilities and configuration

- Template::keyExists(string $property, string $key): bool  
  Check presence in internal maps: 'var', 'choice', 'repeat', 'form'.

- Template::findNodeByAttr(DOMElement $node, string $value, string $attr): ?DOMElement  
  Static recursive search helper to find an element by var/choice/repeat on a subtree.

- Template::$ATTR_VAR, Template::$ATTR_CHOICE, Template::$ATTR_REPEAT  
  Attribute names used. Can be customized if they conflict with your markup.

- Template::$FORM_ELEMENT_NODES  
  Node names treated as form controls.

- Template::$ENABLE_TRACER  
  When enabled, inserted assets may include a trace attribute.

- Template::$REMOVE_CDATA  
  When true, CDATA wrappers are removed from final output.

Example:
```php
<?php
Template::$ATTR_VAR = 'data-var';    // if you prefer custom attributes
Template::$REMOVE_CDATA = true;      // default
```


---

## Common patterns and tips

- Working with multiple nodes for the same var:
```php
foreach ($t->getVarList('tag') as $el) {
      $t->addCss($el, 'highlight');
  }
```


- Conditional content:
```php
$t->setVisible('premium', $user->isPremium());
```


- Cleanly replacing a container’s content while preserving attributes:
```php
$t->replaceHtml('card', '<article class="card">New</article>', preserveAttrs: true);
```


- Appending sub-templates:
```php
$row = Template::load('<li var="row"></li>');
  $row->setText('row', 'Item');
  $t->appendTemplate('list', $row);
```


- Emptying a container:
```php
$t->empty('messages');
```


- Direct DOM access is available via getDocument(false), but prefer the Template API to preserve internal bookkeeping.

---

If you’d like, I can generate a cheat-sheet table or inline PHP examples tailored to your specific template markup. My name is AI Assistant.


