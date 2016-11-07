<?php

namespace Dom;

/**
 * A PHP5 DOM Template Library
 *
 * NOTE: `var` names should begin with '__' because they are
 *   considered reserved for the template system's internal functions.
 *
 * Caching: After long discussions and a number of tests regarding
 *   the caching of templates, it has been decided to not implement
 *   caching at this level. Developers can implement their own method
 *   of caching in their projects. This has been decided because the
 *   template system has been optimized for speed and there is a
 *   feeling that caching will introduce non required overhead.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
 */
class Template
{


    /**
     * Enable addition of data-tracer attributes to inserted JS and CSS
     * @var bool
     */
    static $enableTracer = false;

    /**
     * Customised array of node names or attribute names to collect the nodes for.
     * For example:
     *   Node Name = 'module': All \DOMElements with the name <module></module> will be captured
     *   Attr Name = '@attr-name': All \DOMElements containing the attr name 'attr-name' will be captured
     *
     * This can be set statically <b>after</b> the session is set.
     *
     * @var \DOMElement[]
     */
    static $capture = array();

    /**
     * The main template document
     * @var \DOMDocument
     */
    protected $document = null;

    /**
     * A copy of the original un-parsed template
     * @var \DOMDocument
     */
    protected $original = null;

    /**
     * @var string
     */
    private $serialOrigDoc = '';

    /**
     * @var string
     */
    private $serialDoc = '';
    
    
    

    /**
     * An array of var \DOMElement objects
     * @var \DOMElement[]
     */
    protected $var = array();

    /**
     * An array of choice \DOMElement objects
     * @var \DOMElement[]
     */
    protected $choice = array();

    /**
     * An array of repeat \DOMElement objects
     * @var \DOMElement[]
     */
    protected $repeat = array();

    /**
     * An array of form \DOMElement objects
     * @var \DOMElement[]
     */
    protected $form = array();

    /**
     * An array of formElement \DOMElement objects
     * @var \DOMElement[]
     */
    protected $formElement = array();

    /**
     * An array of all custom captured \DOMElement objects
     * @var \DOMElement[]
     */
    protected $captureList = array();

    /**
     * The head tag of a html page
     * @var \DOMElement
     */
    protected $head = null;

    /**
     * The body tag of a html page
     * @var \DOMElement
     */
    protected $body = null;

    /**
     * The head tag of a html page
     * @var \DOMElement
     */
    protected $title = null;

    /**
     * @var \DOMElement[]
     */
    protected $idList = array();

    /**
     * Comment tags to be removed
     * @var \DOMElement[]
     */
    protected $comments = array();

    /**
     * An internal list of nodes to delete after init()
     * @var \DOMNode[]
     */
    private $delete = array();
    
    

    /**
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * Header elements to be added
     * @var array
     */
    protected $headers = array();

    /**
     * Set to true if this template has been parsed
     * @var bool
     */
    protected $parsed = false;

    /**
     * Set to true if this template uses HTML5
     * @var bool
     */
    protected $isHtml5 = false;

    /**
     * Remove CDATA tags from output
     * @var bool
     */
    protected $cdataRemove = true;

    /**
     * Replace multi newlines with one.
     * @var bool
     */
    protected $newlineReplace = true;
    
    
    
    

    /**
     * The constructor
     *
     * @param \DOMDocument $doc
     * @param string $encoding
     */
    public function __construct($doc, $encoding = 'UTF-8')
    {
        $this->init($doc, $encoding);
    }

    
    public function __sleep()
    {
        $this->serialDoc = $this->document->saveXML();
        $this->serialOrigDoc = $this->original->saveXML();
        return array('serialDoc', 'serialOrigDoc', 'encoding', 'headers', 'parsed', 'isHtml5');
    }

    public function __wakeup()
    {
        $doc = new \DOMDocument();
        $doc->loadXML($this->serialDoc);
        $this->init($doc, $this->encoding);

        $this->original = new \DOMDocument();
        $this->original->loadXML($this->serialOrigDoc);
        $this->serialDoc = '';
        $this->serialOrigDoc = '';
    }
    
    public function __clone()
    {
        $this->parsed = false;
        $this->init(clone $this->original, $this->encoding);
    }


    /**
     * Make a template from a file
     *
     * @param string $filename
     * @param string $encoding
     * @throws Exception
     * @return Template
     */
    static function loadFile($filename, $encoding = 'UTF-8')
    {
        if (!is_file($filename)) {
            throw new Exception('Cannot locate XML/XHTML file: ' . $filename);
        }
        $html = file_get_contents($filename);
        $obj = self::load($html, $encoding);
        $obj->document->documentURI = $filename;
        return $obj;
    }

    /**
     * Make a template from a string
     *
     * @param string $html
     * @param string $encoding
     * @throws Exception
     * @return Template
     */
    static function load($html, $encoding = 'UTF-8')
    {
        $html = trim($html);
        if ($html == '' || $html[0] != '<') {
            throw new Exception('Please supply a valid XHTML/XML string to create the \DOMDocument.');
        }
        $isHtml5 = false;
        if ('<!doctype html>' == strtolower(substr($html, 0, 15))) {
            $isHtml5 = true;
        }
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);

        //$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $html = self::cleanXml($html, $encoding);
        $r = $doc->loadXML($html);
        //$r = $doc->loadHTML($html);

        if (!$r) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $e = new Exception('Error Parsing DOM Template');
            $e->setDump($str);
            throw $e;
        }

        $obj = new self($doc, $encoding);
        $obj->isHtml5 = $isHtml5;
        return $obj;
    }

    /**
     * Reset the template to its unedited state
     *
     * @return $this
     */
    public function reset()
    {
        $this->parsed = false;
        $this->init($this->original, $this->encoding);
        return $this;
    }

    /**
     * Reset and prepare the template object.
     * Mainly used for the Repeat objects
     * but could be usefull for your own methods.
     *
     * @param \DOMDocument $doc
     * @param string $encoding
     * @return $this
     */
    public function init($doc, $encoding = 'UTF-8')
    {
        $this->var = array();
        $this->choice = array();
        $this->repeat = array();
        $this->form = array();
        $this->formElement = array();
        $this->idList = array();
        $this->headers = array();
        $this->comments = array();
        $this->head = $this->body = $this->title = null;
        $this->delete = array();

        $this->original = clone $doc;
        $this->document = $doc;
        $this->encoding = $encoding;
        $this->parsed = false;

        $this->prepareDoc($this->document->documentElement);

        foreach ($this->delete as $node) {
            $node->parentNode->removeChild($node);
        }

        $this->delete = array();
        return $this;
    }

    /**
     * A private method to initialise the template.
     *
     * @param \DOMElement $node
     * @param string $form
     */
    private function prepareDoc($node, $form = '')
    {
        if ($this->isParsed()) {
            return;
        }
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if (count(self::$capture)) {
                foreach (self::$capture as $name) {
                    if ($name[0] == '@') {
                        if ($node->hasAttribute(substr($name, 1))) {
                            $this->captureList[$name][] = $node;
                        }
                    } else {
                        if ($node->nodeName == $name) {
                            $this->captureList[$name][] = $node;
                        }
                    }
                }
            }

            // Store all Id nodes.
            if ($node->hasAttribute('id')) {
                $this->idList[$node->getAttribute('id')] = $node;
            }

            // Store all repeat regions
            if ($node->hasAttribute('repeat')) {
                $repeatName = $node->getAttribute('repeat');
                $node->removeAttribute('repeat');
                $this->repeat[$repeatName] = new Repeat($node, $this);
                return;
            }

            // Store all var nodes
            if ($node->hasAttribute('var')) {
                $varStr = $node->getAttribute('var');
                $arr = preg_split('/ /', $varStr);
                foreach ($arr as $var) {
                    if (!array_key_exists($var, $this->var)) {
                        $this->var[$var] = array();
                    }
                    $this->var[$var][] = $node;
                    $node->removeAttribute('var');
                }
            }

            // Store all choice nodes
            if ($node->hasAttribute('choice')) {
                $arr = preg_split('/ /', $node->getAttribute('choice'));
                foreach ($arr as $choice) {
                    if (!array_key_exists($choice, $this->choice)) {
                        $this->choice[$choice] = array();
                        $this->choice[$choice]['node'] = array();
                        $this->choice[$choice]['var'] = array();
                        $this->choice[$choice]['set'] = false;
                    }
                    $this->choice[$choice]['node'][] = $node;
                    $this->choice[$choice]['var'] = array_merge($this->choice[$choice]['var'], $arr);
                }
                $node->removeAttribute('choice');
            }

            // Store all Form nodes
            if ($node->nodeName == 'form') {
                $form = $node->getAttribute('id');
                if ($form == null) {
                    $form = $node->getAttribute('name');
                }
                $this->formElement[$form] = array();
                $this->form[$form] = $node;
            }

            // Store all FormElement nodes
            if ($node->nodeName == 'input' || $node->nodeName == 'textarea' || $node->nodeName == 'select' || $node->nodeName == 'button') {
                $id = $node->getAttribute('name');
                if ($id == null) {
                    $id = $node->getAttribute('id');
                }
                if (!isset($this->formElement[$form][$id])) {
                    $this->formElement[$form][$id] = array();
                }
                $this->formElement[$form][$id][] = $node;
            }

            if ($node->nodeName == 'head') {
                $this->head = $node;
            }
            if ($node->nodeName == 'title' && $this->head) {
                $this->title = $node;
            }
            if ($node->nodeName == 'body') {
                $this->body = $node;
            }
            if (!$this->head) {
                // move all header nodes for compilation
                if ($node->nodeName == 'script' || $node->nodeName == 'style' || $node->nodeName == 'link' || $node->nodeName == 'meta') {
                    if ($node->getAttribute('data-headParse') == 'ignore') {
                        return;
                    }
                    $attrs = array();
                    foreach ($node->attributes as $k => $v) {
                        if ($k == 'var' || $k == 'choice' || $k == 'repeat')
                            continue;
                        $attrs[$k] = $v->nodeValue;
                    }
                    $this->appendHeadElement($node->nodeName, $attrs, $node->textContent);
                    $this->delete[] = $node;
                    return;
                }
            }
            // iterate through the elements
            $children = $node->childNodes;
            foreach ($children as $child) {
                if ($child->nodeType == \XML_COMMENT_NODE) {
                    $this->comments[] = $child;
                }
                $this->prepareDoc($child, $form);
            }
            $form = '';
        }
    }

    /**
     * Get the list of captured \DOMElement nodes
     *
     * @return array
     */
    public function getCaptureList()
    {
        return $this->captureList;
    }

    /**
     * Get the current \DOMDocument character encoding
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * This is the output flag for the \DomDocument
     * if self::OUTPUT_XML the toString() will use the saveXML() method of the \DOMDocument
     * otherwise it will use the saveHTML().
     *
     * Notice: if using HTML5 the saveHTML() will be used always.
     *
     * @param string $mode
     * @return Template
     */
    public function setOutputMode($mode)
    {
        $this->output = $mode;
        return $this;
    }

    /**
     * Return the document file path if one exists.
     * For non file based tempaltes this value will be the same as dirname($_SERVER['PHP_SELF'])
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->document->documentURI;
    }

    /**
     * This will create a new template object containg the
     * HTML/XML content from the first var it finds with the same name
     * Duplicates are ignored.
     *
     * NOTE: This process can be process intensive as you have to iterate the original
     * tempalte node by node looking for the required var node...
     *
     *
     * @param string $var
     * @throws Exception
     * @return \Dom\Template
     */
    public function createTemplateFromVar($var)
    {
        $node = self::findNodeByAttr($this->original->documentElement, $var, 'var');
        if (!$node) {
            throw new Exception('Cannot find var to create a template from.');
        }
        $doc = new \DOMDocument();
        $newNode = $doc->importNode($node, true);
        $doc->appendChild($newNode);
        return new self($doc, $this->getEncoding());
    }
    
    
    /**
     *  Find a node by its var/choice/repeat name
     * 
     * @param \DOMElement $node
     * @param string $attr
     * @param string $value
     * @return \DOMELement
     */
    static function findNodeByAttr($node, $value, $attr = 'var')
    {
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->hasAttribute($attr) && $node->getAttribute($attr) == $value) {
                return $node;
            }
            // iterate through the children
            foreach ($node->childNodes as $child) {
                $found = self::findNodeByAttr($child, $value, $attr);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Replace the text of one or more var nodes
     *
     * @param string $var The var's name.
     * @param string $value The vars value inside the tags.
     * @return Template
     */
    public function insertText($var, $value)
    {
        if (!$this->isWritable('var', $var))
            return $this;

        $nodes = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $this->removeChildren($node);
            $newNode = $this->document->createTextNode($value);
            $node->appendChild($newNode);
        }
        return $this;
    }

    /**
     * Append the text of one or more var nodes
     *
     * @param string $var The var's name.
     * @param string $value The vars value inside the tags.
     * @return Template
     */
    public function appendText($var, $value)
    {
        if (!$this->isWritable('var', $var))
            return $this;

        $nodes = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $newNode = $this->document->createTextNode($value);
            $node->appendChild($newNode);
        }
        return $this;
    }

    /**
     * Get the text inside a var node.
     *
     * @param string $var
     * @return string
     */
    public function getText($var)
    {
        if (!$this->isWritable('var', $var))
            return '';
        $nodes = $this->findVar($var);
        return $nodes[0]->nodeValue;
    }

    /**
     * Add the class if it does not exist
     *
     * @param string $var
     * @param string|array $class
     * @return Template
     */
    public function addClass($var, $class)
    {
        $list = $class;
        if (!is_array($class)) {
            $class = trim($class);
            $list = explode(' ', $class);
        }
        $list2 = explode(' ', $this->getAttr($var, 'class'));
        $list = array_merge($list2, $list);
        $list = array_unique($list);

        $classStr = trim(implode(' ', $list));
        $this->setAttr($var, 'class', $classStr);
        return $this;
    }

    /**
     * remove the class if it exists
     *
     * @param string $var
     * @param string $class
     * @return Template
     */
    public function removeClass($var, $class)
    {
        $str = $this->getAttr($var, 'class');
        $str = preg_replace('/(' . $class . ')\s?/', '', trim($str));
        $this->setAttr($var, 'class', $str);
        return $this;
    }

    /**
     * Retreive the text contained within an attribute of a node.
     *
     * @param string $var
     * @param string $attr
     * @return string
     */
    public function getAttr($var, $attr)
    {
        if (!$this->isWritable('var', $var))
            return '';
        /** @var \DOMElement[] $nodes */
        $nodes = $this->findVar($var);
        if (count($nodes)) {
            return $nodes[0]->getAttribute($attr);
        }
        return '';
    }

    /**
     * Replace an attribute value.
     *
     * @param string $var
     * @param string $attr
     * @param string $value
     * @return Template
     */
    public function setAttr($var, $attr, $value = null)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        if (!$value === null) {
            $value = $attr;
        }
        $nodes = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if ($value === null) {
                $node->removeAttribute($attr);
            } else {
                $node->setAttribute($attr, $value);
            }
        }
        return $this;
    }

    /**
     * Set a choice node to become visible in a document.
     *
     * @param string $choice The name of the choice
     * @return Template
     */
    public function setChoice($choice)
    {
        if ($this->isWritable('choice', $choice)) {
            $this->choice[$choice]['set'] = true;
        }
        return $this;
    }

    /**
     * Set a choice node to become invisible in a document.
     *
     * @param string $choice The name of the choice
     * @return Template
     */
    public function unsetChoice($choice)
    {
        if (!$this->keyExists('choice', $choice)) {
            $this->choice[$choice]['set'] = false;
        }
        return $this;
    }

    /**
     * Return a form object from the document.
     *
     * @param string $id
     * @return Form|null
     */
    public function getForm($id = '')
    {
        if ($this->isWritable()) {
            $form = null;
            if (isset($this->form[$id])) {
                $form = $this->form[$id];
            }
            return new Form($form, $this->formElement[$id], $this);
        }
        return null;
    }

    /**
     * Get a repeating region from a document.
     *
     * @param string $repeat
     * @return Repeat
     */
    public function getRepeat($repeat)
    {
        if ($this->keyExists('repeat', $repeat)) {
            $obj = $this->repeat[$repeat];
            return clone $obj;
        }
        return null;
    }

    /**
     * Get a var element node from the document.
     *
     * @param string $var
     * @return \DOMElement
     */
    public function getVarElement($var)
    {
        $nodes = $this->findVar($var);
        if (is_array($nodes) && count($nodes)) {
            return $nodes[0];
        }
        return $nodes;
    }

    /**
     * Get the repeat node list
     *
     * @return array
     */
    public function getRepeatList()
    {
        return $this->repeat;
    }

    /**
     * Get the choice node list
     *
     * @return array
     */
    public function getChoiceList()
    {
        return $this->choice;
    }

    /**
     * Get a var element node from the document.
     * If no var name is provided the entire var array is returned.
     *
     * @param string $var
     * @return \DOMNode[]
     */
    public function getVarList($var = '')
    {
        $nodes = $this->findVar($var);
        if (is_array($nodes)) {
            return $nodes;
        }
        return $this->var;
    }

    /**
     * Internal method to enable var to be a \DOMElement or array of \DOMElements....
     *
     * @param mixed $var
     * @return array|\DOMElement
     */
    protected function findVar($var)
    {
        if (is_array($var)) {
            if (count($var) && current($var) instanceof \DOMElement) {
                return $var;
            }
        }
        if ($var instanceof \DOMElement) {
            return array($var);
        }
        if ($this->keyExists('var', $var)) {
            return $this->var[$var];
        }
        return null;
    }

    /**
     * Get a \DOMElement from the document based on its unique ID
     * ID attributes should be unique for XHTML documents, multiple names
     * are ignored and only the first node found is returned.
     *
     * @param string $id
     * @return \DOMElement Returns null if not found
     */
    public function getElementById($id)
    {
        return $this->idList[$id];
    }

    /**
     * Return the head node if it exists.
     *
     * @return \DOMElement
     */
    public function getHeadElement()
    {
        return $this->head;
    }

    /**
     * Return the current list of header nodes
     *
     * @return array
     */
    public function getHeaderList()
    {
        return $this->headers;
    }

    /**
     * Set the current list of header nodes
     *
     * @param array
     * @return Template
     */
    public function setHeaderList($arr)
    {
        $this->headers = $arr;
        return $this;
    }

    /**
     * merge existing header array with this template header array
     *
     * @param array $arr
     * @return Template
     */
    public function mergeHeaderList($arr)
    {
        $this->setHeaderList(array_merge($this->headers, $arr));
        return $this;
    }

    /**
     * Return the body node.
     *
     * @return \DOMElement
     */
    public function getBodyElement()
    {
        return $this->body;
    }

    /**
     * Gets the page title text.
     *
     * @return string The title.
     */
    public function getTitleText()
    {
        return $this->title->nodeValue;
    }

    /**
     * Sets the document title text if available.
     *
     * @param string $value
     * @throws Exception
     * @return Template
     */
    public function setTitleText($value)
    {
        if ($this->isWritable()) {
            if ($this->title == null) {
                throw new Exception('This document has no title node.');
            }
            $this->removeChildren($this->title);
            $this->title->nodeValue = $value;
        }
        return $this;
    }

    /**
     * If a title tag exists it will be returned.
     *
     * @return \DOMNode|null
     */
    public function getTitleElement()
    {
        return $this->title;
    }

    /**
     * Appends an element to the widgets of the HTML head element.
     *
     * In the form of:
     *  <$elementName $attributes[$key]="$attributes[$key].$value">$value</$elementName>
     *
     * NOTE: Only allows unique headers. An md5 hash is referenced from all input parameters.
     *  Any duplicate headers are discarded.
     *
     * @param string $elementName
     * @param array $attributes An associative array of (attr, value) pairs.
     * @param string $value The element value.
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return Template
     */
    public function appendHeadElement($elementName, $attributes, $value = '', $node = null)
    {
        if (!$this->isWritable())
            return $this;
        $preKey = $elementName . $value;
        foreach ($attributes as $k => $v) {
            $preKey .= $k . $v;
        }
        $hash = md5($preKey);
        $this->headers[$hash]['elementName'] = $elementName;
        $this->headers[$hash]['attributes'] = $attributes;
        $this->headers[$hash]['value'] = $value;
        $this->headers[$hash]['node'] = $node;
        return $this;
    }

    /**
     * Use this to add meta tags
     *
     * @param string $name
     * @param string $content
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return Template
     */
    public function appendMetaTag($name, $content, $node = null)
    {
        return $this->appendHeadElement('meta', array('name' => $name, 'content' => $content), '', $node);
    }

    /**
     * Append a CSS file to the template header
     *
     * @param string $urlString
     * @param array $attrs
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return $this
     */
    public function appendCssUrl($urlString, $attrs = array(), $node = null)
    {
        if (!$this->isWritable())
            return $this;
        $attrs['rel'] = 'stylesheet';
        $attrs['href'] = $urlString;
        $attrs = $this->addTracer(debug_backtrace(), $attrs);
        $this->appendHeadElement('link', $attrs, '', $node);
        return $this;
    }

    /**
     * Append some CSS text to the template header
     *
     * @param $css
     * @param array $attrs
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return Template
     */
    public function appendCss($css, $attrs = array(), $node = null)
    {
        if (!$this->isWritable())
            return $this;
        $attrs = $this->addTracer(debug_backtrace(), $attrs);
        $this->appendHeadElement('style', $attrs, "\n" . $css . "\n", $node);
        return $this;
    }

    /**
     * Append a Javascript file to the template header
     *
     * @param string $urlString
     * @param array $attrs
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return Template
     */
    public function appendJsUrl($urlString, $attrs = array(), $node = null)
    {
        if (!$this->isWritable())
            return $this;
        $attrs['type'] = 'text/javascript';
        $attrs['src'] = $urlString;
        $attrs = $this->addTracer(debug_backtrace(), $attrs);
        $this->appendHeadElement('script', $attrs, '', $node);
        return $this;
    }

    /**
     * Append some CSS to the template header
     *
     * @param string $js
     * @param array $attrs
     * @param \DOMElement $node If sent this head element will append after the supplied node
     * @return Template
     */
    public function appendJs($js, $attrs = array(), $node = null)
    {
        if (!$this->isWritable())
            return $this;
        $attrs['type'] = 'text/javascript';
        $attrs = $this->addTracer(debug_backtrace(), $attrs);
        $this->appendHeadElement('script', $attrs, $js, $node);
        return $this;
    }

    /**
     * Add the calling trace
     * @param $trace
     * @param $attrs
     * @return mixed
     */
    private function addTracer($trace, $attrs)
    {
        if (self::$enableTracer && !empty($trace[1]) && empty($attrs['data-tracer'])) {
            $attrs['data-tracer'] = (!empty($trace[1]['class']) ? $trace[1]['class'] . '::' : '').(!empty($trace[1]['function']) ? $trace[1]['function'] . '()' : '');
        }
        return $attrs;
    }


    /**
     * Create an importable Node filled with new content
     * Useful for inserting nodes from string
     *
     * @param $markup
     * @param string $encoding
     * @return \DOMElement
     */
    static function makeContentNode($markup, $encoding = 'UTF-8')
    {
        $markup = self::cleanXml($markup, $encoding);
        $id = '_c_o_n__';
        $markup = sprintf('<?xml version="1.0" encoding="%s"?><div xml:id="%s">%s</div>', $encoding, $id, $markup);
        $doc = new \DOMDocument();
        $doc->loadXML($markup);
        return $doc->getElementById($id);
    }



    /**
     * Return the HTML/XML contents of a var node.
     * If there are more than one node with the same var name
     * the first one is selected by default.
     * Use the $idx if there is more than one var block
     *
     * @param string $var
     * @param int $idx
     * @return string
     */
    public function innerHtml($var, $idx = 0)
    {
        if (!$this->isWritable('var', $var))
            return '';
        $nodes = $this->findVar($var);
        $html = $this->getHtml($var);
        $tag = $nodes[$idx]->nodeName;
        return preg_replace('@^<' . $tag . '[^>]*>|</' . $tag . '>$@', '', $html);
    }

    /**
     * Return the html including the node contents
     *
     * @param string $var
     * @return string
     */
    public function getHtml($var)
    {
        if (!$this->isWritable('var', $var))
            return '';
        $nodes = $this->findVar($var);
        $doc = new \DOMDocument();
        $doc->appendChild($doc->importNode($nodes[0], TRUE));
        $html = trim($doc->saveHTML());
        return $html;
    }

    /**
     * Insert HTML formatted text into a var element.
     *
     * @param string $var
     * @param string $html
     * @return Template
     * @warn bug exists where after insertion the template loses
     *   reference to the node in repeat regions. The fix (for now)
     *   is to just do all operations on that var node before this call.
     */
    public function insertHtml($var, $html)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        foreach ($nodes as $i => $node) {
            self::insertHtmlDom($node, $html, $this->encoding);
        }
        return $this;
    }

    /**
     * Static
     * Insert HTML formatted text into a dom element.
     *
     * @param \DOMElement $element
     * @param string $html
     * @param string $encoding
     * @return \DOMElement
     */
    static function insertHtmlDom($element, $html, $encoding = 'UTF-8')
    {
        if ($html == null) {
            return null;
        }

        $elementDoc = $element->ownerDocument;
        while ($element->hasChildNodes()) {
            $element->removeChild($element->childNodes->item(0));
        }
        $html = self::cleanXml($html, $encoding);
        if (substr($html, 0, 5) == '<?xml') {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }

        $contentNode = self::makeContentNode($html);
        foreach ($contentNode->childNodes as $child) {
            $node = $elementDoc->importNode($child, true);
            $element->appendChild($node);
        }
        return $contentNode;
    }

    /**
     * Insert a \DOMDocument into a var element
     * The var tag will not be replaced only its contents
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     */
    public function insertDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $this->removeChildren($node);
            if (!$doc->documentElement)
                continue;
            $newChild = $this->document->importNode($doc->documentElement, true);
            $node->appendChild($newChild);
        }
        return $this;
    }

    /**
     * Parse and Insert a template into a var element
     * The var tag will not be replaced only its contents
     *
     * This will also grab any headers in the supplied template.
     *
     * @param string $var
     * @param Template $template
     * @param bool $parse Set to false to disable template parsing
     * @return Template
     */
    public function insertTemplate($var, Template $template, $parse = true)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $this->mergeHeaderList($template->getHeaderList());
        return $this->insertDoc($var, $template->getDocument($parse));
    }

    /**
     * Replace HTML formatted text into a var element.
     *
     * @param string $var
     * @param string $html
     * @param bool $preserveAttr Set to false to ignore copying of existing Attributes
     * @return Template
     */
    public function replaceHtml($var, $html, $preserveAttr = true)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        foreach ($nodes as $i => $node) {
            $newNode = self::replaceHtmlDom($node, $html, $this->encoding, $preserveAttr);
            if ($newNode) {
                $this->var[$var][$i] = $newNode;
            }
        }
        return $this;
    }

    /**
     * Replace a node with HTML formatted text.
     *
     * @param \DOMElement $element
     * @param string $html
     * @param string $encoding
     * @param bool $preserveAttr Set to false to ignore copying of existing Attributes
     * @return \DOMElement
     */
    static function replaceHtmlDom($element, $html, $encoding = 'UTF-8', $preserveAttr = true)
    {
        if ($html == null) {
            return null;
        }

        $html = self::cleanXml($html, $encoding);
        if (substr($html, 0, 5) == '<?xml') {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }
        $elementDoc = $element->ownerDocument;

        /** @var \DOMElement $contentNode */
        $contentNode = self::makeContentNode($html);
        $contentNode = $contentNode->firstChild;
        $contentNode = $elementDoc->importNode($contentNode, true);
        if ($element->hasAttributes() && $preserveAttr && $contentNode->nodeType == \XML_ELEMENT_NODE) {
            foreach ($element->attributes as $attr) {
                $contentNode->setAttribute($attr->nodeName, $attr->nodeValue);
            }
        }
        $element->parentNode->replaceChild($contentNode, $element);
        return $contentNode;
    }

    /**
     * Replace a node with the supplied \DOMDocument
     * The \DOMDocument's topmost node will be used to replace the destination node
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     */
    public function replaceDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        if (!$doc->documentElement) {
            return $this;
        }
        foreach ($nodes as $i => $node) {
            $newNode = $this->document->importNode($doc->documentElement, true);
            $node->parentNode->replaceChild($newNode, $node);
            if (is_string($var)) {
                $this->var[$var][$i] = $newNode;
            }
        }
        return $this;
    }

    /**
     * Replace a var node with the supplied Template
     * The \DOMDocument's topmost node will be used to replace the destination node
     *
     * This will also copy any headers in the supplied template.
     *
     * @param string $var
     * @param Template $template
     * @throws Exception
     * @return Template
     */
    public function replaceTemplate($var, Template $template)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        if (!$template instanceof Template) {
            throw new Exception('Invalid Template Object');
        }
        $this->mergeHeaderList($template->getHeaderList());
        return $this->replaceDoc($var, $template->getDocument());
    }

    /**
     * Append HTML formatted text into a var element.
     *
     * @param string $var
     * @param string $html
     * @return Template
     */
    public function appendHtml($var, $html)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        foreach ($nodes as $i => $node) {
            self::appendHtmlDom($node, $html, $this->encoding);
        }
        return $this;
    }

    /**
     * Append HTML text into a dom node.
     *
     * @param \DOMElement $element
     * @param string $html
     * @param string $encoding
     * @return bool Returns true on success
     */
    static function appendHtmlDom($element, $html, $encoding = 'UTF-8')
    {
        if ($html == null) {
            return null;
        }

        $html = self::cleanXml($html, $encoding);
        if (substr($html, 0, 5) == '<?xml') {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }
        $elementDoc = $element->ownerDocument;

        $contentNode = self::makeContentNode($html);
        foreach ($contentNode->childNodes as $child) {
            $node = $elementDoc->importNode($child, true);
            $element->appendChild($node);
        }
        return $contentNode;
    }

    /**
     * Append documents to the var node
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     */
    public function appendDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        /** @var \DOMElement $el */
        foreach ($nodes as $el) {
            $node = $this->document->importNode($doc->documentElement, true);
            $el->appendChild($node);
        }
        return $this;
    }

    /**
     * Append a template to a var element, it will parse the template before appending it
     * This will also copy any headers in the $template.
     *
     * @param string $var
     * @param Template $template
     * @return Template
     */
    public function appendTemplate($var, Template $template)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $this->mergeHeaderList($template->getHeaderList());
        return $this->appendDoc($var, $template->getDocument());
    }

    /**
     * Prepend a template to a var element, it will parse the template before appending it
     * This will also copy any headers in the $template.
     *
     * @param string $var
     * @param Template $template
     * @return Template
     */
    public function prependTemplate($var, Template $template)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $this->mergeHeaderList($template->getHeaderList());
        return $this->prependDoc($var, $template->getDocument());
    }

    /**
     * Prepend documents to the var node
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     */
    public function prependDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);

        /** @var \DOMElement $el */
        foreach ($nodes as $el) {
            $node = $this->document->importNode($doc->documentElement, true);
            if ($el->firstChild) {
                $el->insertBefore($node, $el->firstChild);
            } else {
                $el->appendChild($node);
            }
        }
        return $this;
    }

    /**
     * Append HTML formatted text into a var element.
     *
     * @param string $var
     * @param string $html
     * @return Template
     */
    public function prependHtml($var, $html)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach ($nodes as $i => $node) {
            self::prependHtmlDom($node, $html);
        }
        return $this;
    }

    /**
     * Append HTML text into a dom node.
     *
     * @param \DOMElement $element
     * @param string $html
     * @return bool Returns true on success
     */
    static function prependHtmlDom($element, $html)
    {
        if ($html == null) {
            return null;
        }
        $elementDoc = $element->ownerDocument;

        $contentNode = self::makeContentNode($html);

        /** @var \DOMElement $child */
        foreach ($contentNode->childNodes as $child) {
            $node = $elementDoc->importNode($child, true);
            if ($element->firstChild) {
                $element->insertBefore($node, $element->firstChild);
            } else {
                $element->appendChild($node);
            }
        }
        return $contentNode;
    }

    /**
     * Get the parsed state of the template.
     * If true then no more changes can be made to the template
     *
     * @return bool
     */
    public function isParsed()
    {
        return $this->parsed;
    }

    /**
     * Return a parsed \Dom document.
     * After using this call you can no longer use the template render functions
     * as no changes will be made to the template unless you use DOM functions
     *
     * @param bool $parse Set to false to avoid parsing and return \DOMDocument in its current state
     * @return \DOMDocument
     */
    public function getDocument($parse = true)
    {
        if (!$this->isParsed() && $parse) {
            foreach ($this->comments as $node) {
                // Keep the IE comment control statements
                if (!$node || !isset($node->parentNode) || !$node->parentNode || !$node->ownerDocument ) {
                    continue;
                }
                if ($node->nodeName == null || preg_match('/^\[if /', $node->nodeValue)) {
                    continue;
                }
                if ($node->parentNode->nodeName != 'script' && $node->parentNode->nodeName != 'style') {
                    $node->parentNode->removeChild($node);
                }
            }
            /** @var Repeat $repeat */
            foreach ($this->repeat as $name => $repeat) {
                $node = $repeat->getRepeatNode();
                if (!$node || !isset($node->parentNode) || !$node->parentNode) {
                    continue;
                }
                $node->parentNode->removeChild($node);
                unset($this->repeat[$name]);
            }
            foreach ($this->choice as $name => $nodes) {
                // TODO: we are not handling double choices IE: choice="admin staff"
                //  In this case if one is set then the node should not be removed.
                //  The below code does not work like this......   ????????
                //  Also needs to take into account unsetChoice()  ????????
                if (!$nodes['set']) {
                    foreach ($nodes['node'] as $node) {
                        if (!$node || !isset($node->ownerDocument)) {       // ??? whats the need for ownerDocument ???
                            continue;
                        }
                        if ($node && $node->parentNode) {
                            $node->parentNode->removeChild($node);
                        }
                    }
                }
                unset($this->choice[$name]);
            }
            // Insert headers
            if ($this->head) {
                $meta = array();
                $other = array();
                foreach ($this->headers as $i => $header) {
                    if ($header['elementName'] == 'meta') {
                        $meta[$i] = $header;
                    } else {
                        $other[$i] = $header;
                    }
                }
                $ordered = array_merge($meta, $other);
                foreach ($ordered as $header) {
                    $node = $this->document->createElement($header['elementName']);
                    if ($header['value'] != null) {
                        $ct = $this->document->createCDATASection("\n" . trim($header['value']) . "\n");
                        $node->appendChild($ct);
                    }
                    if (isset($header['attributes'])) {
                        foreach ($header['attributes'] as $k => $v) {
                            $node->setAttribute($k, $v);
                        }
                    }

                    $nl = $this->document->createTextNode("\n");
                    if ($header['node']) {
                        $n = $header['node'];
                        $n->parentNode->insertBefore($node, $n);
                        $n->parentNode->insertBefore($nl, $n);
                    } else {
                        $this->head->appendChild($node);
                        $this->head->appendChild($nl);
                    }
                }
            }
            $this->parsed = true;

            $this->document->formatOutput = true;
            $this->document->preserveWhiteSpace = false;
            $this->document->normalizeDocument();
        }

        return $this->document;
    }

    /**
     * Removes all children from a node.
     *
     * @param \DOMNode $node
     * @return $this
     */
    protected function removeChildren($node)
    {
        while ($node->hasChildNodes()) {
            $node->removeChild($node->childNodes->item(0));
        }
        return $this;
    }

    /**
     * Check if a repeat,choice,var,form (template property) Exists.
     *
     * @param string $property
     * @param string $key
     * @return bool
     */
    public function keyExists($property, $key)
    {
        if (!array_key_exists($key, $this->$property)) {
            return false;
        }
        return true;
    }

    /**
     * Check if a repeat,choice,var,form (template property) exist,
     * and if the document has ben parsed.
     *
     *
     * @param string $property
     * @param string $key
     * @return bool
     */
    public function isWritable($property = '', $key = '')
    {
        if ($this->isParsed())
            return false;
        if ($property && $key && is_string($key)) {
            if (!$this->keyExists($property, $key))
                return false;
        }
        return true;
    }

    /**
     * Return a string from an object.
     *
     * @param mixed $obj
     * @return string
     * @deprecated Objects should use the __toString magic method
     */
    static function objectToString($obj)
    {
        if (is_object($obj) && method_exists($obj, 'toString')) {
            return $obj->toString();
        } else if (is_object($obj) && method_exists($obj, '__toString')) {
            return $obj->__toString();
        }
        return $obj;
    }

    /**
     * Receive the document in the format of 'xml' or 'html'.
     *
     * @param bool $parse parse the document
     * @return string
     */
    public function toString($parse = true)
    {
        $doc = $this->getDocument($parse);
        $str = $doc->saveXML($doc->documentElement);

        // Cleanup Document
        if (substr($str, 0, 5) == '<?xml') {    // Remove xml declaration
            $str = substr($str, strpos($str, "\n") + 1);
        }
        if ($this->isHtml5 && strtolower(substr($str, 0, 15)) != '<!doctype html>') {
            $str = "<!DOCTYPE html>\n" . $str;
        }
        // fix allowable non closeable tags
        $str = preg_replace_callback('#<(\w+)([^>]*)\s*/>#s', 
          function ($m) {
            $xhtml_tags = array("br", "hr", "input", "frame", "img", "area", "link", "col", "base", "basefont", "param", "meta");
            return in_array($m[1], $xhtml_tags) ? "<$m[1]$m[2] />" : "<$m[1]$m[2]></$m[1]>";
          }, $str );
        
        if ($this->cdataRemove)
            $str = str_replace(array('><![CDATA[', ']]><'), array('>', '<'), $str);
        if ($this->newlineReplace)
            $str = preg_replace ('/\s+$/m', "\n", $str);
        
        return $str;
    }

    /**
     * Return a string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Get the xml/html and return the cleaned string
     * A good place to clean any nasty html entities and other non valid XML/XHTML elements
     *
     * @param string $xml
     * @param string $encoding
     * @return string
     */
    static function cleanXml($xml, $encoding = 'UTF-8')
    {
        static $mapping = null;
        if (!$mapping) {
            $list1 = get_html_translation_table(HTML_ENTITIES, ENT_NOQUOTES, $encoding);
            $list2 = get_html_translation_table(HTML_SPECIALCHARS, ENT_NOQUOTES, $encoding);
            $list = array_merge($list1, $list2);
            $mapping = array();
            foreach ($list as $char => $entity) {
                $mapping[strtolower($entity)] = '&#' . self::ord($char) . ';';
            }
            //$extras = array('&times;' => '&#215;', '&copy;' => '&#169;', '&nbsp;' => '&#160;', '&raquo;' => '&#187;', '&laquo;' => '&#171;');
            $extras = array('&times;' => '&#215;');
            $mapping = array_merge($mapping, $extras);
        }
        $xml = str_replace(array_keys($mapping), $mapping, $xml);
        $xml = preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $xml);
        return $xml;
    }

    /**
     * Since PHP's ord() function is not compatible with UTF-8
     * Here is a workaround.... GGRRR!!!!
     *
     * @param string $ch
     * @return integer
     */
    static private function ord($ch)
    {
        $k = mb_convert_encoding($ch, 'UCS-2LE', 'UTF-8');
        $k1 = ord(substr($k, 0, 1));
        $k2 = ord(substr($k, 1, 1));
        return $k2 * 256 + $k1;
    }
}

/**
 * A repeat region is a sub template of a parent templates nodes.
 *
 */
class Repeat extends Template
{

    /**
     * @var \DOMElement
     */
    protected $repeatNode = null;

    /**
     * @var string
     */
    protected $repeatName = '';

    /**
     * @var Template
     */
    protected $repeatParent = null;

    /**
     * __construct
     *
     * @param \DOMElement $node
     * @param Template $parent
     */
    public function __construct($node, Template $parent)
    {
        $this->repeatNode = $node;
        $this->repeatName = $node->getAttribute('repeat');
        $this->repeatParent = $parent;

        $repeatDoc = new \DOMDocument();
        $tplNode = $repeatDoc->importNode($node, true);
        $repeatDoc->appendChild($tplNode);

        parent::__construct($repeatDoc, $parent->getEncoding());
    }

    /**
     * Re init the template when clone is called
     */
    public function __clone()
    {
        $this->init(clone $this->original, $this->encoding);
    }

    /**
     * Append a repeating region to the document.
     * Repeating regions are appended to the supplied var.
     * If the var is null or '' then the repeating region is appended
     * to is original location in the parent template.
     *
     * @param string $var
     * @param Template $destRepeat
     * @return \DOMElement The inserted node
     */
    public function appendRepeat($var = '', Template $destRepeat = null)
    {
        if (!$this->isWritable()) {
            return null;
        }

        $this->repeatParent->setHeaderList(array_merge($this->repeatParent->getHeaderList(), $this->getHeaderList()));

        $appendNode = $this->repeatNode;
        if ($var) {
            if ($this->repeatParent) {
                $appendNode = $this->repeatParent->getVarElement($var);
            }
            if ($destRepeat && $destRepeat->getVarElement($var)) {
                $appendNode = $destRepeat->getVarElement($var);
            }
        }

        $insertNode = $appendNode->ownerDocument->importNode($this->getDocument()->documentElement, true);

        if ($appendNode->parentNode) {
            if (!$var) {
                $appendNode->parentNode->insertBefore($insertNode, $appendNode);
                return $insertNode;
            }
        }

        $appendNode->appendChild($insertNode);
        return $insertNode;
    }

    /**
     * Append a repeating region to the document.
     * Repeating regions are appended to the supplied var.
     * If the var is null or '' then the repeating region is appended
     * to is original location in the parent template.
     *
     * @param string $var
     * @param Template $destRepeat
     * @return \DOMElement The inserted node
     */
    public function prependRepeat($var = '', Template $destRepeat = null)
    {
        if (!$this->isWritable()) {
            return null;
        }
        $this->repeatParent->setHeaderList(array_merge($this->repeatParent->getHeaderList(), $this->getHeaderList()));
        $appendNode = $this->repeatNode;
        if ($var) {
            if ($this->repeatParent) {
                $appendNode = $this->repeatParent->getVarElement($var);
            }
            if ($destRepeat && $destRepeat->getVarElement($var)) {
                $appendNode = $destRepeat->getVarElement($var);
            }
        }
        $insertNode = $appendNode->ownerDocument->importNode($this->getDocument()->documentElement, true);

        return $insertNode;
    }

    /**
     * Return the repeat node...
     *
     * @return \DOMElement
     */
    public function getRepeatNode()
    {
        return $this->repeatNode;
    }

    /**
     * get the parent template this repeat belongs to.
     *
     * @return Template
     */
    public function getParentTemplate()
    {
        return $this->repeatParent;
    }

}
