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

// Register the parser to load for all template forms call \Dom\Template::clearTemplateParsers() to reset.
\Dom\Template::addTemplateParser(\Dom\Parser\FormParser::class);

// Load the template containing the form markup.
$template = \Dom\Template::load($html);

// Get the form from the parser object.
$formParser = $template->getParser(\Dom\Parser\FormParser::class);
$domForm = $formParser->getForm();

// ...

// To reset the template parser queue.
\Dom\Template::clearTemplateParsers();

```


## Create a Parser?

View the existing `\Dom\Parser\FormParser` class for an example of how to create a parser.