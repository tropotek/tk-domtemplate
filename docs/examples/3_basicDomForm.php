<?php
// Start the output buffer
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>PHP Dom Template (PDT) Library</title>
  <link rel="stylesheet" type="text/css" href="stylesheet.css" />
</head>

<body>

  <div id="content">
    <h1 var="pageTitle">Default Text</h1>
    <h3 class="contentHeader" var="pageTitle">Default Text</h3>
    <div class="contentMain">
      <p>An example Contact Form</p>

      <p>&#160;</p>
      <p choice="success" class="notice">Message sent successfully.</p>
      <p choice="success" class="notice">No email send because its turned off.</p>
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

      <p>&#160;</p>
      <p>&#160;</p>

    </div>
  </div>



</body>
</html>
<?php
// include the Template lib
include_once dirname(dirname(dirname(__FILE__))) . '/lib/Dom/Template.php';

// Create a template from the html in the buffer
$buff = ob_get_clean();
$template = Dom_Template::load($buff);

// Set the pageTitle tag  --> <h1 var="pageTitle">Default Text</h1>
$template->insertText('pageTitle', 'Dynamic Form Example');


$domForm = $template->getForm('contactForm');


// Initalise any form elements to a default status
$select = $domForm->getFormElement('country');
/* @var $select Dk_Dom_FormSelectElement */
$select->appendOption('-- Select --', '');
$select->appendOption('New Zealand', 'NZ');
$select->appendOption('England', 'UK');
$select->appendOption('Australia', 'AU');
$select->appendOption('America', 'US');
$select->setValue('AU');

// process the form
if (isset($_REQUEST['process'])) {
    // If you are up to it reload the form values
    $domForm->getFormElement('name')->setValue($_REQUEST['name']);
    $domForm->getFormElement('email')->setValue($_REQUEST['email']);
    $domForm->getFormElement('country')->setValue($_REQUEST['country']);
    $domForm->getFormElement('comments')->setValue($_REQUEST['comments']);


    // Do some basic validation
    $email = $_REQUEST['email'];
    if (!preg_match('/^[0-9a-zA-Z]([-_.]*[0-9a-zA-Z])*@[0-9a-zA-Z]([-.]?[0-9a-zA-Z])*$/', $email)) {
        $template->insertText('email-error', 'Invalid email.');
        $template->setChoice('email-error');
    } else {
        // If all is valid send a quick email (Please note that this is for demo pourposes only and you must check for security)
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
            //mail($email, 'Email from ' . $_REQUEST['name'] . '(' . $_REQUEST['country'] . ')', $_REQUEST['comments']);
            $template->setChoice('success');
        } else {
            die('You must not use this form as a mailgate!');
        }
    }
}

echo $template->toString();
