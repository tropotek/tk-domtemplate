<?php
// Start the output buffer
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PHP Dom Template (PDT) Library</title>

    <link rel="stylesheet" type="text/css" href="stylesheet.css" />
</head>
<body>
    <div id="content">
        <h1 var="pageTitle">Default Text</h1>
        <h3 class="contentHeader" var="pageTitle">Default Text</h3>
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
                        <td class="label">Country</td>
                        <td class="input">
                            <select name="country"></select>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Multiple</td>
                        <td class="input">
                            <select name="cars[]" multiple="multiple"></select>
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
            <p>&#160;</p>

            <div choice="success">
              <pre var="formData"></pre>
            </div>

        </div>

        <div class="footer">
            <p class="home"><a href="index.html">Home</a></p>
            <p class="copyright"><a href="http://www.domtemplate.com" target="_blank">Copyright 2008 PHP DOMTemplate</a></p>
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

\Dom\Template::addTemplateParser(\Dom\Parser\FormParser::class);
$template = \Dom\Template::load($html);
$formParser = $template->getParser(\Dom\Parser\FormParser::class);

$domForm = $formParser->getForm('contactForm');

// Set the pageTitle tag  --> <h1 var="pageTitle">Default Text</h1>
$template->setText('pageTitle', 'Dynamic Form Example');

// Init any form elements to a default status
$select = $domForm->getFormElement('country');
/* @var $select \Dom\Form\Select */
$select->appendOption('-- Select --', '');
$select->appendOption('New Zealand', 'NZ');
$select->appendOption('England', 'UK');
$select->appendOption('Australia', 'AU');
$select->appendOption('America', 'US');
$select->setValue('AU');

$select = $domForm->getFormElement('cars[]');
$select->appendOption('Red', 'red');
$select->appendOption('Yellow', 'yellow');
$select->appendOption('Green', 'green');
$select->appendOption('Blue', 'blue');
$select->setValue(['red', 'blue']);

// process the form
if (isset($_REQUEST['process'])) {
    // Populate the form with the submitted values
    $domForm->getFormElement('name')->setValue($_REQUEST['name']);
    $domForm->getFormElement('email')->setValue($_REQUEST['email']);
    $domForm->getFormElement('country')->setValue($_REQUEST['country']);
    $domForm->getFormElement('cars[]')->setValue($_REQUEST['cars']);
    $domForm->getFormElement('comments')->setValue($_REQUEST['comments']);

    // Do some basic validation
    $email = $_REQUEST['email'];
    if (!preg_match('/^[0-9a-zA-Z]([-_.]*[0-9a-zA-Z])*@[0-9a-zA-Z]([-.]?[0-9a-zA-Z])*$/', $email)) {
        $template->setText('email-error', 'Invalid email.');
        $template->setVisible('email-error');
    } else {
        // TODO: Send your email here!!!

        $template->setVisible('success');
        $template->setText('formData', print_r($_REQUEST, true));
    }
}

echo $template->toString();
