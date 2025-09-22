# Template Parsers

Template Parsers should be used sparingly and only when you need to 
extend the functionality of the `\Dom\Template` class.

To modify the final Template parsed DOM it is recommended to use a 
[Template Modifier](domModifier.md).

!!! Warning
    Having too many registered parsers can slow down the template loading and parsing process.

## Registering a Parser

Parsers are registered with the `\Dom\Template` object statically so that you can register them once for multiple 
Template instances.

```php
<?php

// ...

// register the form parser to access form nodes
$formParser = new FormParser();
\Dom\Parser::instance()->registerParser($formParser);

$template = \Dom\Template::load($html);

// optional: remove the registered form parsers, do not do this if you want to create more templates implementing the same parsers.
// \Dom\Parser::instance()->clear();

$domForm = $formParser->getForm();

// ...

```


## Create a Parser?

View the existing `\Dom\Parser\FormParser` class for an example of how to create a parser.