# PHP DomTemplate :boom: 

__Project:__ [ttek/tk-domtemplate](http://packagist.org/packages/ttek/tk-domtemplate)
__Web:__ <http://www.domtemplate.com/>  
__Authors:__ Michael Mifsud <http://www.tropotek.com/>  
  
A PHP5 DOM Template engine for XHTML/XML


## Contents

- [Installation](#installation)
- [Introduction](#introduction)
- [VAR](#var)
- [CHOICE](#choice)
- [REPEAT](#repeat)
- [Forms](#form)
- [Misc Functions](#misc-functions)
- [Auto Renderer](#autorenderer)
- [Loader](#loader)
- [PHP Examples](docs/examples/)


## Installation

Available on Packagist ([ttek/tk-domtemplate](http://packagist.org/packages/ttek/tk-domtemplate))
and as such installable via [Composer](http://getcomposer.org/).

```bash
composer require ttek/tk-domtemplate
```

Or add the following to your composer.json file:

```json
"ttek/tk-domtemplate": "~3.2"
```

If you do not use Composer, you can grab the code from GitHub, and use any
PSR-0 compatible autoloader to load the classes.

## Introduction

__NOTE: This engine uses the PHP DOM module that requires that all documents
loaded into it must be strict [XML/XHTML markup](https://en.wikipedia.org/wiki/XHTML). Close all tags and ensure
all & are &amp;amp;, even in URL query strings.__

The DOM template engine has been developed so designers have a simple
way to communicate to build templates and communicate their requirements
to developers.

There are three custom attributes the template engine uses. These are:

 1. __[var](#var)__: Is used to allow to add attributes and content to a node.
 2. __[choice](#choice)__: Is used to hide/show a node and its contents
 3. __[repeat](#repeat)__: For repeating data like lists or tables.

Do not be concerned that these attributes do not meet the HTML5 spec or some other spec
because they are removed once the template is parsed.

That's all there is to it from a designers point of view. For a developer it
makes interacting with HTML template easy without overriding any of the designers hard work.

PHP DOMTemplate also comes with a number of other features that help when rendering forms, css, javascript
metatags, etc. The following sections will outline how to use these. Also check out the code examples to 
see how we have used the DOMTemplate.


## VAR

This is the `var` attribute. This us used in a node if you want to modify its content or attributes.
 The following is an example of a `var` being used within a template:

```html
<div><a href="#" var="link"></a></div>
```

With this template the developer can then build coe to manipulate this node how they see fit:

```php
<?php
// Load a new template from a file. (The file must be XHTML valid or errors will be produced) 
$template = new \Dom\Template::loadFile('index.html');

// Add some text content inside the anchor node
$template->insertText('link', 'This is a link');

//Add some HTML content inside the ancor node
$template->insertHtml('link', '<i class="fa fa-times"></i> Close');

// Add a real URL to the ancor
$template->setAttr('link', 'href', 'http://www.example.com/');

...
```

## CHOICE
A `choice` attribute allows for the removal of a dom node.
If the attribute exists then the node is removed by default. you must call setChoice().
See the example below.

```html
<div choice="showNode"><a href="#" var="link"></a></div>
```

so by default this node would be removed from the DOM tree. To keep it visible simply use:

```php
<?php
// Load a new template from a file. (The file must be XHTML valid or errors will be produced) 
$template = new \Dom\Template::loadFile('index.html');

// Add some text content inside the anchor node
$template->setVisible('showNode');

...
```


## REPEAT
A `repeat` attribute is used for repeating data such as lists or tables. The `repeat` blocks can contain nested `var`, `choice`, `repeat`
nodes as well.
When retreiving the `repeat` object from a template it is important to note that the repeat object is a subClass of the Template object
and thus has the same functionality with the added extra call to appendRepeat(); that is called when you are finished rendering a `repeat`
 and want it appended to its parent template node.
See the example below.

```html
<ul>
  <li repeat="item" var="item"><a href="#" var="url">Link</a></li>
</ul>
```

With the repeat markup set you can then go ahead and populate your list or table.

```php
<?php
// Load a new template from a file. (The file must be XHTML valid or errors will be produced) 
$template = new \Dom\Template::loadFile('index.html');

$list = array(
  'Link 1' => 'http://www.example.com/link1.html',
  'Link 2' => 'http://www.example.com/link2.html',
  'Link 3' => 'http://www.example.com/link3.html',
  'Link 4' => 'http://www.example.com/link4.html'
);

// Loop through the data and render each item
foreach($list as $text => $url) {
  $repeat = $template->getRepeat('item');
  
  $repeat->insertText('url', $text);
  $repeat
  
  // Finish the repeat item and append it to its parent.
  $repeat->appendRepeat();
}

...
```



## FORM

Forms are handled a little differently with the DOMTemplate object. You do not need any vars or choices to access a form element node, but you can if you wish.

If we are given the following basic form:

```html
<form id="contactForm" method="post">
  <table>
    <tr>
      <td class="label">Name:</td>
      <td class="input"><input type="text" name="name" /></td>
    </tr>
    <tr>
      <td class="label">Email:</td>
      <td class="input">
        <p class="formError" choice="email-error" var="email-error" />
        <input type="text" name="email" />
      </td>
    </tr>
    <tr>
      <td class="label">Country</td>
      <td class="input">
        <select name="country"></select>
      </td>
    </tr>
    <tr>
      <td class="label">Comments:</td>
      <td class="input"><textarea name="comments" rows="5" cols="40"></textarea></td>
    </tr>
    <tr>
      <td class="label">&#160;</td>
      <td class="input"><input type="submit" name="process" value="Submit"/></td>
    </tr>
  </table>
</form>
```

Then we can access the form through the code lik this:

```php
<?php
$template = \Dom\Template::load($buff);

// Set the pageTitle tag  --> <h1 var="pageTitle">Default Text</h1>
$template->setText('pageTitle', 'Dynamic Form Example');

$domForm = $template->getForm('contactForm');
// Init any form elements to a default status
$select = $domForm->getFormElement('country');
/* @var $select \Dom\Form\Select */
$select->appendOption('-- Select --', '');
$select->appendOption('New Zealand', 'NZ');
$select->appendOption('England', 'UK');
$select->appendOption('Australia', 'AU');
$select->appendOption('America', 'US');
$select->setValue('AU');

...

// Then you can set the value from the request if you like....
$domForm->getFormElement('name')->setValue($_REQUEST['name']);
$domForm->getFormElement('email')->setValue($_REQUEST['email']);
$domForm->getFormElement('country')->setValue($_REQUEST['country']);
$domForm->getFormElement('comments')->setValue($_REQUEST['comments']);

```


## Misc Methods 

For CSS and Javascript we have added some unique methods, these allow you to call the insertTemplate(), \
appendTemplate(), insertDoc(), appendDoc(), etc..
methods and the javascript and CSS will be inserted into the parents <head> tag. This allows you
insert these scripts or URLS anywhere in the rendering process as long as the final parent template has a Head tag.

 - appendCss(): 
```
$template->appendCss('body > p {background: #00FF00; }');
```
 - appendCssUrl(): 
```
$template->appendCssUrl('http://example.com/css/style.css');
```
 - appendJs(): 
```
$js = <<<JS
jQuery(function ($) {
    $('.act').click(function (e) {
        return confirm('Are you sure you want to install this plugin?');
    });
});
JS;
$template->appendJs($js);
```
 - appendJsUrl(): 
```
$template->appendJsUrl('http://example.com/js/sctipt.css');
```
   
This functionality is fantastic if you want to iterate over the DOMTemplate just before displaying the document
and manipulate all the CSS or Javascript nodes.



Other functions of the DomTemplate include:

 - getElementById(): Retrieve a node via its ID attribute.
 - setTitleText(): Set the &lt;title&gt; tag text if the tag exists.
 - appendMetaTag(): will append a meta tag to the parent template if a &lt;head&gt; tag exists.



## Loader

The loader object gives the developer the ability to search for alternate templates before loading the 
supplied template. This is handy when you want to be able to give users the ability to override existing
default templates. Adapters can be added/created that search for alternate templates based on your own frameworks needs.

First you need to setup the Loader and add any adapters that will look for existing templates. This uses a LIFO queue.
So the Last added Adapter is the first to be executed.
```php
<?php
// * Setup the Template loader, create adapters to look for templates as needed
/* @var \Dom\Loader $dl */
$dl = \Dom\Loader::getInstance();
$dl->addAdapter(new \Dom\Loader\Adapter\DefaultLoader());
$dl->addAdapter(new \Dom\Loader\Adapter\ClassPath($config->getAppPath().'/html/xml'));
```

Then later you can retrieve it to load all your apps templates:
```php
<?php
$tplFile = \Tk\Config::getInstance()->getTemplatePath().'/index.html';
$template = \Dom\Loader::loadFile($tplFile);
```



## AutoRenderer (deprecated)

__WHY?__ As I am not a fan of making the DOMTemplate Lib use any type of internal scripting logic, 
which will add a new layer of complexity for the designer, I have terminated this as a supported part 
of the DOMTemplate lib. 

<small>_It is left here as a reference only, use it as a base to get yourself started if you want to build 
on it for your own requirements._</small>

---

The auto renderer was built to facilitate automatic rendering of data similar to that
of other templating languages.

Data is passed to the auto renderer and template attributes are used to display the selected data stored in 
the AutoRenderer.
 
[See the Example](docs/examples/autoRenderer.php)