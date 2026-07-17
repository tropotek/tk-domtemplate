# PHP DomTemplate :boom: 

__Project:__ [ttek/tk-domtemplate](http://packagist.org/packages/ttek/tk-domtemplate)  
__Authors:__ Michael Mifsud <http://www.tropotek.com/>  
__Documentation:__ <http://domtemplate.tropotek.com/>  



## Introduction

The PHP [\Dom\Template](http://domtemplate.tropotek.com/) wraps the DOMDocument and provides a fast, attribute-driven way to render
HTML templates. We did not want another template language within a language, but a way to use PHP
to render HTML templates keeping the render logic within PHP and the style and layout logic in HTML.

An important feature for us was the template layout and design were kept in the HTML so that it could be easily
edited by designers and not be affected by what the developers in the team are doing. Making communication
between developers and designers relatively easy for most tasks.

The \Dom\Template system enables developers to develop and share a basic markup structure, designers can then
see visually and understand what the minimum markup requirements are.
Saving on communication time and allowing designers to focus on making the project look great.

Features:

- Mark “variables” and “choices” in your HTML and set their content or visibility
- Manage repeating regions for table row insertion
- Insert/append/replace HTML fragments
- Add CSS/JS (inline or external) and meta/head elements
- Compose pages by merging templates, other DOMDocument's, or HTML strings

Key ideas:

- Use attributes in your HTML to mark nodes:
 - var: mark nodes whose content/attributes you’ll set
 - choice: mark nodes hidden that you can show in your renderer
 - repeat: define repeating regions
- Work with the template (set text/HTML, attributes, headers) before parsing.
- Call `toString()` or `getDocument()` to parse the template and return a renderable DOMDocument or HTML string.



## Installation

Available on Packagist ([ttek/tk-domtemplate](http://packagist.org/packages/ttek/tk-domtemplate))
and as such installable via [Composer](http://getcomposer.org/).

```bash
$ composer require ttek/tk-domtemplate
```

Or add the following to your composer.json file:

```json
{
  "require": {
    "ttek/tk-domtemplate": "~8.1"
  }
}
```


## Basic Template Usage

```php
<?php
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP Dom Template (PDT) Library</title>
</head>
<body>
  <div id="content">
    <h1>Hello World</h1>
    <p var="helloWorld">Default Text</p>

    <p>&nbsp;</p>
  </div>
</body>
</html>
<?php
// Include lib, you should use use composer if available.
$basepath = dirname(__FILE__, 3);
include_once $basepath . '/vendor/autoload.php';


// Create a template from the html in the buffer
$html = trim(ob_get_clean());
$template = \Dom\Template::load($buff);

// Add some style
$template->appendCssUrl('stylesheet.css');

// Add some javascript
$template->appendJs('alert();');

// Set some dynamic text
$template->setText('helloWorld', 'This is the `Hello World` Dynamic text.');

echo $template->toString();
```


## Contributing

If you wish to contribute updates to the code or documentation, we are open to any requests.

Use the [GitHub discussions](https://github.com/tropotek/tk-domtemplate/discussions) area to chat about what you would like to see or contribute.

