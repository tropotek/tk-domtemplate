PHP DomTemplate
=========
Published: 01 Jul 2007

Authors:
  * Michael Mifsud <http://www.tropotek.com/>

A PHP5 DOM Templating engine for HTML/XML

Requirements
------------

- PHP5+
- git/svn/hg depending on which repositories you want to support

Installation
------------

1. Clone the repository and include the Dom folder files into your PHP
   project or autoloader (PSR0 compatable).
2. Use composer and include the package "ttek/tk-domtemplate": "~2.0"

You should now be able to use the template classes.

__NOTE: This engine uses the PHP DOM module that requires that all documents
loaded into it must be strict XML/XHTML markup. Close all tags and ensure
all & are &amp;amp;.__

Basic Usage
------------

The DOM template engine has been developed so designers have a simple
way to communicate to build templates and communicate their requirements
to developers.

There are three custom attributes the template engine uses. These are:

 1. __var__: Is used to allow developers to add attributes and content to a node.
 2. __choice__: Is used to allow a developer to hide/show a node and its contents
 3. __repeat__: For repeating data like lists or table data.

Do not be concerned that these attributes do not meet the HTML5 spec or some other spec
because they will be removed once the template is parsed and displayed.

That's all there is to it from a designers point of view. For a developer it
makes interacting with HTML/XML files a breeze too.

Lets look at an example that uses all of these new attributes.

    <?php
    // Start the output buffer
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>

    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <title>PHP Dom Template</title>
      <link rel="stylesheet" type="text/css" href="stylesheet.css" />
    </head>

    <body>

      <div id="content">

        <h1 var="pageTitle">Default Text</h1>
        <h3 class="contentHeader" var="pageTitle">Default Text</h3>

        <div class="contentMain">
          <p var="content01"></p>
          <p var="content02"></p>
          <p>&#160;</p>
          <p choice="notShown">This text should stay hidden</p>
          <p>&#160;</p>
          <ul choice="list">
            <li repeat="listRow"><a href="#" var="listUrl">Default Link</a></li>
          </ul>
          <p>&#160;</p>
          <p>This is a static paragraph</p>
        </div>

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
    include_once 'Dom/Template.php';

    // Create a template from the html in the buffer
    $buff = ob_get_clean();
    $template = Dom_Template::load($buff);

    // Set the pageTitle tag  --> <h1 var="pageTitle">Default Text</h1>
    $template->insertText('pageTitle', 'Dynamic Form Example');

    // Set the pageTitle tag  --> <h1 var="pageTitle">Default Text</h1>
    $template->insertText('pageTitle', 'Dynamic Page Title');

    // Add some dynamic page content  --> <p var="content01"></p>
    $content = sprintf('<b>Dynamic Text</b> Phasellus metus lorem, ornare non; aliquam convallis, luctus sed, sem.
    Cras vel urna nec magna euismod sollicitudin. Morbi vehicula. Nunc consequat.
    In hac habitasse platea dictumst.');
    $template->appendText('content01', $content);
    $template->appendHtml('content02', $content);

    // Add some list data --> <ul choice="list">...
    $listData = array('http://www.tropotek.com/' => 'Tropotek Home Page', 'http://www.phpdruid.com/' => 'PHPDruid Home Page', 'http://www.domtemplate.com' => 'Php Dom Template');
    if (count($listData) > 0) {
        $template->setChoice('list');
    }
    foreach ($listData as $url => $value) {
        $repeat = $template->getRepeat('listRow');
        $repeat->insertText('listUrl', $value);
        $repeat->setAttr('listUrl', 'href', $url);
        $repeat->setAttr('listUrl', 'title', $value);
        $repeat->setAttr('listUrl', 'onclick', 'window.open(this.href);return false;');
        $repeat->append();
    }


    $domForm = $template->getForm('contactForm');

    // Initialise any form elements to a default status
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
            // If all is valid send a quick email (Please note that this is for demo purposes only and you must check for security)
            if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
                //mail($email, 'Email from ' . $_REQUEST['name'] . '(' . $_REQUEST['country'] . ')', $_REQUEST['comments']);
                $template->setChoice('success');
            } else {
                die('You must not use this form as a mailgate!');
            }
        }
    }

    echo $template->toString();
    ?>

Look over this example and we will add the API and more examples soon.

Enjoy!

