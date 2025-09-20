# Template Modifiers

Template modifiers are used to modify the DOM after it has been parsed and before it is rendered.

There are a number of modifiers that are built into the Template framework.

- ClearComments: Used to remove all HTML comments from the DOM.
- Scss: Compile and cache linked .scss files into .css files
- JsLast: Used to move all JavaScript code to the bottom of the body tag.
- PageBytes: Used to calculate the size of the page in bytes.
- UrlPath: searches for links and prepends them with the current site path

!!! Warning
    Some of the current Modifiers do require libraries that are not included in the Template framework.
    You will need to install their dependencies manually.


## Using Template Modifiers

```php

$dm = new Modifier();
$dm->addModifier(new Modifier\ClearComments());

$html = <<<HTML
<div>
    <!-- comment -->
    <p>Hello World</p>
</div>
HTML;

$template = \Dom\Template::load($html);

// This will remove the comments from the DOM
echo $dm->execute($template->getDocument());

```

## Create a Template Modifier

!!! Note
    We are working on a guide for creating Template Modifiers.
    For now you can look at the source code for the existing modifiers to create your own.



