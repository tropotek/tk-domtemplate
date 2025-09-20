# Template Builder

The Builder is used to extract and create Templates from a page of markdown.

## Using the Builder

For example, if we want to build a suite of templates for a form and its fields. 
We could use the following HTML file `form.html`:

```html
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Dom Template Builder</title>
</head>
<body class="bg-white">

  <!-- Main Form container -->
  <form method="post" class="tk-form g-3" novalidate="novalidate"
        data-opt-valid-css="is-valid"  data-opt-error-css="is-invalid"
        id="tpl-form" var="form">
  </form>

  <div>
    <!-- default fieldset template if no repeat defined in the form -->
    <fieldset class="col-12" id="tpl-form-fieldset">
      <legend var="legend"></legend>
      <div class="row" var="fields"></div>
    </fieldset>

    <input type="hidden" var="element" id="tpl-form-hidden">

    <div var="field" id="tpl-form-input">
      <label class="form-label" var="label"></label>
      <input type="text" class="form-control" var="element">
      <div class="invalid-feedback" choice="error"></div>
      <div class="form-text text-secondary" choice="notes"></div>
    </div>

    <div var="field" id="tpl-form-input-group">
      <label class="form-label" var="label"></label>
      <div class="input-group" var="is-error input-group">
          <span class="input-group-text" choice="pre"></span>
          <input type="text" class="form-control" var="element">
          <span class="input-group-text" choice="post"></span>
      </div>
      <div class="invalid-feedback" choice="error"></div>
      <div class="form-text text-secondary" choice="notes"></div>
    </div>
  </div>

</body>
</html>
```

Using the Builder object, we can now extract individual templates using their id attributes:
```php
$builder = new Builder('form.html');

$formTemplate = $builder->getTemplate('tpl-form');

$hiddenTemplate = $builder->getTemplate('tpl-form-hidden');

$inputTemplate1 = $builder->getTemplate('tpl-form-input');
$inputTemplate2 = $builder->getTemplate('tpl-form-input');
$inputTemplate3 = $builder->getTemplate('tpl-form-input');

// ....

```




