
# Getting Started with Dom\Form

This guide introduces the Dom\Form API and its element wrappers to help you read and 
manipulate form markup from a Dom\Template.

## Overview

- Dom\Form wraps a single form element and provides helpers to:
    - Check submit state
    - Configure action/method/target
    - Access and mutate form controls by name
    - Manage hidden fields
- Individual controls are exposed via element wrappers (Input, Textarea, Select) that operate on the underlying DOM nodes.

Typical flow:

- Load a Dom\Template that contains a form
- Get a Dom\Form instance using the `\Dom\Parser\FormParser`
- Read and update fields, add hidden inputs, set action/method
- Validate and process form submission
- Render the template

## Get A Form Object

Forms and elements are not natively available in Dom\Template. 
To enable the \Dom\Form lib, you must register the [\Dom\Parser\FormParser](domParser.md):

```php
<?php

// ...

// Register the parser to load for all template forms call \Dom\Template::clearTemplateParsers() to reset.
\Dom\Template::registerParser(\Dom\Parser\FormParser::class);
// Load the template containing the form markup.
$template = \Dom\Template::load($html);
// Get the form from the parser object.
$formParser = $template->getParser(\Dom\Parser\FormParser::class);

// Get the DOM form object.
$domForm = $formParser->getForm('contactForm');

// Start using the form
$domForm->setAction('form.php')->setMethod('post');

// Get an element
$select = $domForm->getFormElement('country');

// etc...

```


## Accessing Form Elements

- Single element by name (and optional index):
```php
<?php
$el = $form->getFormElement('email');       // Dom\Form\Input|Textarea|Select|null
$el2 = $form->getFormElement('choice[]', 1);  // second input named "choice[]"
```

- Multiple elements by name:
```php
<?php
$list = $form->getFormElementList('choice'); // array of element wrappers
```

- Existence and counts:
```php
<?php
$form->formElementExists('email'); // bool
$form->getNumFormElements('choice'); // int
$form->getElementNames(); // string[]
```


## Form Attributes

```php
<?php
$form
    ->setAction('/submit')
    ->setMethod('post')     // 'get' or 'post'
    ->setTarget('_self');   // '_blank' | '_self' | '_parent' | '_top'
```


## Hidden Fields

- Append a hidden input:
```php
<?php
$form->appendHiddenElement('csrf', $token);
```


- Get all hidden inputs:
```php
<?php
$hidden = $form->getHiddenElements(); // array of Dom\Form\Input
```


## Radio/Checkbox Helpers

- Mark a radio (or checkbox) by value for a given name:
```php
<?php
$form->setCheckedByValue('color', 'blue'); // checks input[name=color][value=blue], unchecks other radios
```


## Form Node and Identification

```php
<?php
$form->getName();      // string (name attribute)
$form->getId();        // string (id attribute)
$form->getNode();      // \DOMElement (<form>)
$form->getTemplate();  // Dom\Template (owning template)
```


## Element Wrappers

Element wrappers provide type-appropriate APIs over form controls. 
Below are common operations; actual capabilities depend on the wrapper:

- Input
    - Get/set value
    - Get/set attributes (type, name, checked, disabled, etc.)
    - For checkbox/radio: set checked state

- Textarea
    - Get/set text content (value)
    - Get/set attributes (name, rows, cols, disabled, etc.)

- Select
    - Get/set selected option(s)
    - Add/remove options
    - Toggle multiple, disabled, etc.

Example usage pattern:
```php
<?php
// Input
if ($email = $form->getFormElement('email')) {
    $email->setValue('user@example.com');
}

// Textarea
if ($bio = $form->getFormElement('bio')) {
    $bio->setValue('Tell us about yourself...');
}

// Select
if ($country = $form->getFormElement('country')) {
    $country->setSelectedValue('US'); // for single select
}
```


## Integration with Dom\Template

- Build your form in HTML within a template.
- Obtain Dom\Form from your integration point, then:
    - Pre-fill defaults (values, checked/selected states)
    - Add hidden fields (e.g., CSRF)
    - Configure action/method/target
    - Render the template

Minimal example sketch:

```
<?php ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PHP Dom Template (PDT) Library</title>
</head>
<body>
    <div id="content">
        <h1 var="pageTitle">Default Title</h1>
        <div class="contentMain">
            <p>An example Contact Form</p>
            <p choice="success" class="notice">Message sent successfully.</p>
            <form id="contactForm" method="post">
                <table>
                    <tr>
                        <td class="label">Name:</td>
                        <td class="input"><input type="text" name="name"/></td>
                    </tr>
                    <tr>
                        <td class="label">Email:</td>
                        <td class="input">
                            <p class="formError" choice="email-error" var="email-error"></p>
                            <input type="text" name="email"/>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Comments:</td>
                        <td class="input"><textarea name="comments" rows="5" cols="40"></textarea></td>
                    </tr>
                    <tr>
                        <td class="label">&#160;</td>
                        <td class="input"><button type="submit" name="action" value="process">Send</button></td>
                    </tr>
                </table>
            </form>
            <p>&#160;</p>

        </div>
    </div>
</body>
</html>
<?php
// Include lib, you should use use composer if available.
$basepath = dirname(__FILE__, 3);
include_once $basepath . '/vendor/autoload.php';

// Create a template from the html in the buffer
$html = ob_get_clean();

\Dom\Template::registerParser(\Dom\Parser\FormParser::class);
$template = \Dom\Template::load($html);
$formParser = $template->getParser(\Dom\Parser\FormParser::class);

$domForm = $formParser->getForm('contactForm');
$domForm->setMethod('post');

// process the form
if (($_REQUEST['action'] ?? '') === 'process') {
    // Populate the form with the submitted values
    $domForm->getFormElement('name')->setValue($_REQUEST['name']);
    $domForm->getFormElement('email')->setValue($_REQUEST['email']);
    $domForm->getFormElement('comments')->setValue($_REQUEST['comments']);

    // Do any form validation
    $email = $_POST['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $template->setText('email-error', 'Invalid email.');
        $template->setVisible('email-error');
    } else {
        // TODO: Send your email here!!!
        $template->setVisible('success');
        $template->setText('formData', print_r($_REQUEST, true));
    }
}

echo $template->toString();

```



