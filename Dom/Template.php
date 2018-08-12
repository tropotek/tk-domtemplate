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
 *
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @license Copyright 2007
 *
 * @todo BUG: found if you declare a nested var on a node then replace the parent var with another template
 * @todo      this removed the child var nodes and then when you try to modify them the DOM engine
 * @todo      errors with "Couldn't fetch DOMElement", have not notice this happening before the 2.0.15 update
 *
 */
class Template
{

    const ATTR_HIDDEN = '__tk-dom-template--hide';


    /**
     * Enable addition of data-tracer attributes to inserted JS and CSS
     * @var bool
     */
    public static $enableTracer = false;

    /**
     * @var null|\Psr\Log\LoggerInterface
     * @since 2.2.26
     */
    public static $logger = null;

    /**
     * Customised array of node names or attribute names to collect the nodes for.
     * For example:
     *   Node Name = 'module': All DOMElements with the name <module></module> will be captured
     *   Attr Name = '@attr-name': All DOMElements containing the attr name 'attr-name' will be captured
     *
     * This can be set statically <b>after</b> the session is set.
     *
     * @var \DOMElement[]
     */
    public static $capture = array();


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
     * An array of var DOMElement objects
     * @var \DOMElement[][]
     */
    protected $var = array();

    /**
     * An array of repeat DOMElement objects
     * @var \DOMElement[][]
     */
    protected $repeat = array();

    /**
     * deprecated: An array of choice DOMElement objects
     * This array now stores all vars that are to be removed or ols choices that are set
     * @var array|\DOMElement[][]
     * @remove 2.2.0
     */
    protected $choice = array();



    /**
     * An array of form DOMElement objects
     * @var \DOMElement[]
     */
    protected $form = array();

    /**
     * An array of formElement DOMElement objects
     * @var \DOMElement[]
     */
    protected $formElement = array();

    /**
     * An array of all custom captured DOMElement objects
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
     * Elements to be appended to the <head> tag
     * @var array
     */
    protected $headers = array();

    /**
     * Templates to be appended to the <body> tag
     * @var array
     */
    protected $bodyTemplates = array();

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
     * An array of errors thrown
     * @var string[]|array
     */
    protected $errors = array();


    /**
     * @var null|callable
     * @since 2.2.0
     */
    protected $onPreParse = null;

    /**
     * @var null|callable
     * @since 2.2.0
     */
    protected $onPostParse = null;

    /**
     * Blocking var to avoid a callback recursive loop
     * @var bool
     * @since 2.2.0
     */
    private $parsing = false;

    /**
     * Set to true if this template has been parsed
     * @var bool
     */
    protected $parsed = false;
    
    
    

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
     * Make a template from a string
     *
     * @param string $html
     * @param string $encoding
     * @throws Exception
     * @return Template
     */
    public static function load($html, $encoding = 'UTF-8')
    {
        $html = trim($html);
        if ($html == '' || $html[0] != '<') {
            throw new Exception('Please supply a valid XHTML/XML string to create the DOMDocument.');
        }
        $isHtml5 = false;
        if ('<!doctype html>' == strtolower(substr($html, 0, 15))) {
            $isHtml5 = true;
        }
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);

        //$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $html = self::cleanXml($html, $encoding);
        $ok = $doc->loadXML($html);
        if (!$ok) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $str .= "\n\n" . $html . "\n";
            $e = new Exception('Error Parsing DOM Template', 0, null, $str);
            throw $e;
        }

        $obj = new self($doc, $encoding);
        $obj->isHtml5 = $isHtml5;
        return $obj;
    }

    /**
     * Make a template from a file
     *
     * @param string $filename
     * @param string $encoding
     * @return Template
     * @throws Exception
     */
    public static function loadFile($filename, $encoding = 'UTF-8')
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
     * This will create a new template object containing the
     * HTML/XML content from the first var it finds with the same name
     * Duplicates are ignored.
     *
     * NOTE: This process can be process intensive as you have to iterate the original
     * template node by node looking for the required var node...
     *
     *
     * @param string $var
     * @return \Dom\Template
     * @throws Exception
     */
    public function createTemplateFromVar($var)
    {
        $node = self::findNodeByAttr($this->original->documentElement, $var, 'var');
        if (!$node) {
            //\Tk\Log::error('Cannot find var to create a template from: ' . $var);
            throw new Exception('Cannot find var to create a template from: ' . $var);
        }
        $doc = new \DOMDocument();
        $newNode = $doc->importNode($node, true);
        $doc->appendChild($newNode);
        return new self($doc, $this->getEncoding());
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
        //$this->choice = array();
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
                /** @var string $name */
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
                $this->repeat[$repeatName] = new Repeat($node, $this);
                if (!array_key_exists($repeatName, $this->var)) {
                    $this->var[$repeatName] = array();
                }
                $this->var[$repeatName][] = $node;
                $node->removeAttribute('repeat');
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

            if ($node->hasAttribute('choice')) {
                $arr = preg_split('/ /', $node->getAttribute('choice'));
                foreach ($arr as $choice) {
                    if (!array_key_exists($choice, $this->choice)) {
                        $this->choice[$choice] = array();
                    }
                    $this->choice[$choice][] = $node;
                    $node->setAttribute(self::ATTR_HIDDEN, 'true');
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
     * Get the list of captured DOMElement nodes
     *
     * @return array
     */
    public function getCaptureList()
    {
        return $this->captureList;
    }

    /**
     * Get the current DOMDocument character encoding
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Return the document file path if one exists.
     * For non file based templates this value will be the same as dirname($_SERVER['PHP_SELF'])
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->document->documentURI;
    }
    
    /**
     *  Find a node by its var/choice/repeat name
     * 
     * @param \DOMElement $node
     * @param string $attr
     * @param string $value
     * @return \DOMELement
     */
    public static function findNodeByAttr($node, $value, $attr = 'var')
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
     * @param $var
     * @param $class
     * @return Template
     */
    public function addClass($var, $class) {

        $list = $class;
        if (!is_array($class)) {
            $class = trim($class);
            $list = explode(' ', $class);
        }
        $list2 = explode(' ', $this->getAttr($var, 'class'));
        $list = array_merge($list2, $list);
        $list = array_unique($list);

        $classStr = trim(implode(' ', $list));
        if ($classStr)
            $this->setAttr($var, 'class', $classStr);
        return $this;
    }

    /**
     * @param $var
     * @param $class
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
     * Replace an attribute value.
     *
     * @param string $var
     * @param string|array $attr
     * @param string|null $value
     * @return Template
     */
    public function setAttr($var, $attr, $value = null)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        if (!is_array($attr)) $attr = array($attr => $value);

        $nodes = $this->findVar($var);
        /* @var \DOMElement $node */
        foreach ($nodes as $node) {
            if (!$node) continue;
            foreach ($attr as $k => $v) {
                if (!$k) continue;
                if ($v === null) $v = $k;
                    $node->setAttribute($k, $v);
            }
        }
        return $this;
    }

    /**
     * Retrieve the text contained within an attribute of a node.
     *
     * @param string $var
     * @param string $attr
     * @return string
     */
    public function getAttr($var, $attr)
    {
        if (!$this->isWritable('var', $var))
            return '';
        /* @var \DOMElement[] $nodes */
        $nodes = $this->findVar($var);
        if (count($nodes)) {
            return $nodes[0]->getAttribute($attr);
        }
        return '';
    }

    /**
     * Remove an attribute
     *
     * @param string $var
     * @param string $attr
     * @return Template
     */
    public function removeAttr($var, $attr)
    {
        return $this->setAttr($var, $attr);
    }


    /**
     * Return a form object from the document.
     *
     * @param string|int $id
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
     * @return null|Repeat
     */
    public function getRepeat($repeat)
    {
        if ($this->keyExists('repeat', $repeat)) {
            /** @var Repeat $obj */
            $obj = $this->repeat[$repeat];
            return clone $obj;
        }
        return null;
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
     * Internal method to enable var to be a DOMElement or array of DOMElements...
     *
     * @param mixed $var
     * @return array|\DOMElement[]
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
        return array();
    }

    /**
     * Get a var element node from the document.
     * If no var name is provided the entire var array is returned.
     *
     * @param string $var
     * @return array|\DOMElement[]
     * @since 2.2.30
     */
    public function get($var = null)
    {
        if ($var) {
            $nodes = $this->findVar($var);
            return $nodes;
        }
        return $this->var;
    }

    /**
     * It is recommended to use hide($var) unless you specifically want to remove the node from the tree.
     * This cannot be undone and you will not be able to show the var unless you re-insert the node
     *
     * @param string $var
     * @return Template
     * @since 2.2.30
     */
    public function remove($var)
    {
        $list = $this->findVar($var);
        /** @var \DOMElement $node */
        foreach($list as $node) {
            $node->parentNode->removeChild($node);
        }
        return $this;
    }

    /**
     * Check if this document has a var
     * @param string $var
     * @return bool
     */
    public function has($var)
    {
        $nodes = $this->findVar($var);
        if (is_array($nodes) && count($nodes)) {
            return true;
        }
        return false;
    }

    /**
     * Show a hidden var
     *
     * @param string $var
     * @since 2.0.15
     * @return Template
     */
    public function show($var)
    {
        $nodes = $this->findVar($var);
        foreach ($nodes as $node) {
            if ($node->hasAttribute(self::ATTR_HIDDEN))
                $node->removeAttribute(self::ATTR_HIDDEN);
        }
        return $this;
    }

    /**
     * Remove a var from the template, this will not remove the node until it is parsed
     * so calling show($var) before the template is parsed will undo this action
     *
     * @param string $var
     * @since 2.0.15
     * @return Template
     */
    public function hide($var)
    {
        $nodes = $this->findVar($var);
        foreach ($nodes as $node) {
            if ($node->hasAttribute(self::ATTR_HIDDEN))
                $node->setAttribute(self::ATTR_HIDDEN, self::ATTR_HIDDEN);
        }
        return $this;
    }

    /**
     * Get a DOMElement from the document based on its unique ID
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
     * Return the root document node.
     * IE: DomDocument->documentElement
     *
     * @return \DOMElement
     */
    public function getRootElement()
    {
        return $this->document->documentElement;
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
     * @return Template
     */
    public function setTitleText($value)
    {
        if ($this->isWritable()) {
            if ($this->title == null) {
                $this->logNotice(__CLASS__.'::setTitleText() This document has no title node.');
                return $this;
            }
            $this->removeChildren($this->title);
            $this->title->nodeValue = htmlentities(html_entity_decode($value));
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
     * Appends an element to the widgets of the HTML head element.
     *
     * In the form of:
     *  <$elementName $attributes[$key]="$attributes[$key].$value">$value</$elementName>
     *
     * NOTE: Only allows unique headers. An md5 hash is referenced from all input parameters.
     *  Any duplicate headers are discarded.
     *
     * I this template does not have a <head> tag the elements will be added to
     * any parent templates that this template is appended/inserted/prepended etc to.
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
        $ignore = array('content', 'type');
        foreach ($attributes as $k => $v) {
            if (in_array($k, $ignore)) continue;
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
     * Add the calling trace to the node
     *
     * @param array $trace
     * @param array $attrs
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
     * Return the body node.
     *
     * @return \DOMElement
     */
    public function getBodyElement()
    {
        return $this->body;
    }

    /**
     * Return the current list of header nodes
     *
     * @return array|Template[]
     */
    public function getBodyTemplateList()
    {
        return $this->bodyTemplates;
    }

    /**
     * Set the current list of header nodes
     *
     * @param array|Template[]
     * @return Template
     */
    public function setBodyTemplateList($arr)
    {
        $this->bodyTemplates = $arr;
        return $this;
    }

    /**
     * Append a template to the <body> tag, the supplied template
     * will be merged into other templates until a <body> tag exists
     *
     * @param Template $template
     * @return $this
     */
    public function appendBodyTemplate($template)
    {
        if (!$this->isWritable())
            return $this;
        $this->bodyTemplates[] = $template;
        return $this;
    }


    /**
     * @param Template $template
     * @return Template
     */
    protected function mergeTemplate($template)
    {
        $this->mergeHeaderList($template->getHeaderList());
        $this->mergeBodyTemplateList($template->getBodyTemplateList());
        return $this;
    }

    /**
     * merge existing header array with this template header array
     *
     * @param array|Template[] $arr
     * @return Template
     */
    public function mergeBodyTemplateList($arr)
    {
        //if (count($this->bodyTemplates)) {
            $this->setBodyTemplateList(array_merge($this->bodyTemplates, $arr));
        //}
        return $this;
    }

    /**
     * merge existing header array with this template header array
     *
     * @param array|Template[] $arr
     * @return Template
     */
    public function mergeHeaderList($arr)
    {
        //if (count($this->headers)) {
            $this->setHeaderList(array_merge($this->headers, $arr));
        //}
        return $this;
    }

    /**
     * Remove all child nodes from a var
     * @param string $var
     * @return Template
     * @since 2.2.24
     */
    public function clear($var)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        /* @var \DOMElement $node */
        foreach ($nodes as $node) {
            $this->removeChildren($node);
        }
        return $this;
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
        /* @var \DOMElement $node */
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
        /* @var \DOMElement $node */
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
        if (count($nodes)) {
            return $nodes[0]->textContent;
        }
        return '';
    }

    /**
     * @param string $var
     * @param string $text
     * @return Template
     * @since v2.0.15
     */
    public function setText($var, $text)
    {
        return $this->insertText($var, $text);
    }

    /**
     * Return the html including the node contents
     *
     * @param string $var
     * @return string
     * @since v2.0.15
     */
    public function getHtml($var)
    {
        $html = '';
        if (!$this->isWritable('var', $var))
            return $html;
        $nodes = $this->findVar($var);
        if (count($nodes)) {
            $doc = new \DOMDocument();
            $doc->appendChild($doc->importNode($nodes[0], true));
            $html = trim($doc->saveHTML());
        }
        return $html;
    }

    /**
     * Set the inner HTML of a node
     * @param string $var
     * @param string $html
     * @return Template
     * @since 2.0.15
     */
    public function setHtml($var, $html)
    {
        $this->clear($var);
        try {
            return $this->appendHtml($var, $html);
        } catch (Exception $e) {
            $this->logError($e->__toString());
        }
    }


    // ---------------- REPLACE --------------------

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
            try {
                $newNode = self::replaceHtmlDom($node, $html, $this->encoding, $preserveAttr);
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
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
     * @throws Exception
     */
    public static function replaceHtmlDom($element, $html, $encoding = 'UTF-8', $preserveAttr = true)
    {
        if ($html == null) {
            return null;
        }

        $html = self::cleanXml($html, $encoding);
        if (substr($html, 0, 5) == '<?xml') {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }
        $elementDoc = $element->ownerDocument;

        /* @var \DOMElement $contentNode */
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
     * Replace a node with the supplied DOMDocument
     * The DOMDocument's topmost node will be used to replace the destination node
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     */
    public function replaceDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        if (!$doc->documentElement) {
            return $this;
        }
        $nodes = $this->findVar($var);
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
     * The DOMDocument's topmost node will be used to replace the destination node
     *
     * This will also copy any headers in the supplied template.
     *
     * @param string $var
     * @param Template $template
     * @return Template
     */
    public function replaceTemplate($var, $template)
    {
        if (!$this->isWritable('var', $var) || !$template)
            return $this;
        if (!$template instanceof Template) {
            \Tk\Log::error('Invalid Template Object For: ' . $var);
            return $this;
        }
        $this->mergeTemplate($template);
        return $this->replaceDoc($var, $template->getDocument());
    }



    // ----------------------  APPEND --------------------------------------------

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
            try {
                self::appendHtmlDom($node, $html, $this->encoding);
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML text into a dom node.
     *
     * @param \DOMElement $element
     * @param string $html
     * @param string $encoding
     * @return \DOMElement|null
     * @throws Exception
     */
    public static function appendHtmlDom($element, $html, $encoding = 'UTF-8')
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

        //if (!$doc->childNodes->length) return $this;

        $nodes = $this->findVar($var);
        /* @var \DOMElement $el */
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
    public function appendTemplate($var, $template)
    {
        if (!$this->isWritable('var', $var) || !$template)
            return $this;
        $this->mergeTemplate($template);
        return $this->appendDoc($var, $template->getDocument());
    }


    // ---------------------- PREPEND -------------------------


    /**
     * Prepend a template to a var element, it will parse the template before appending it
     * This will also copy any headers in the $template.
     *
     * @param string $var
     * @param Template $template
     * @return Template
     */
    public function prependTemplate($var, $template)
    {
        if (!$this->isWritable('var', $var) || !$template)
            return $this;
        $this->mergeTemplate($template);
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

        /* @var \DOMElement $el */
        foreach ($nodes as $el) {
            if (!$doc->documentElement) continue; 
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
        /* @var \DOMElement $node */
        foreach ($nodes as $i => $node) {
            try {
                self::prependHtmlDom($node, $html);
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML text into a dom node.
     *
     * @param \DOMElement $element
     * @param string $html
     * @return \DOMElement|null
     * @throws Exception
     */
    public static function prependHtmlDom($element, $html)
    {
        if ($html == null) {
            return null;
        }
        $elementDoc = $element->ownerDocument;
        $contentNode = self::makeContentNode($html);
        /* @var \DOMElement $child */
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
     * Create an importable Node filled with new content
     * Useful for inserting nodes from string
     *
     * @param $markup
     * @param string $encoding
     * @return \DOMElement
     * @throws Exception
     */
    public static function makeContentNode($markup, $encoding = 'UTF-8')
    {
        $markup = self::cleanXml($markup, $encoding);
        $id = '_c_o_n__';
        $xml = sprintf('<?xml version="1.0" encoding="%s"?><div xml:id="%s">%s</div>', $encoding, $id, $markup);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml);
        if (!$ok) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $str .= "\n\n" . $markup . "\n";
            $e = new Exception('Error Parsing DOM Template', 0, null, $str);
            throw $e;
        }

        return $doc->getElementById($id);
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
     * @return callable|null
     * @since 2.2.0
     */
    public function getOnPreParse()
    {
        return $this->onPreParse;
    }

    /**
     * Add a callable function on pre document parsing
     *
     * EG: function ($template) { }
     *
     * @param callable|null $onPreParse
     * @return Template
     * @since 2.2.0
     */
    public function setOnPreParse($onPreParse)
    {
        $this->onPreParse = $onPreParse;
        return $this;
    }

    /**
     * @return callable|null
     * @since 2.2.0
     */
    public function getOnPostParse()
    {
        return $this->onPostParse;
    }

    /**
     * Add a callable function on post document parsing
     *
     * EG: function ($template) { }
     *
     * @param callable|null $onPostParse
     * @return Template
     * @since 2.2.0
     */
    public function setOnPostParse($onPostParse)
    {
        $this->onPostParse = $onPostParse;
        return $this;
    }

    /**
     * Return a parsed \Dom document.
     * After using this call you can no longer use the template render functions
     * as no changes will be made to the template unless you use DOM functions
     *
     * @param bool $parse Set to false to avoid parsing and return DOMDocument in its current state
     * @return \DOMDocument
     */
    public function getDocument($parse = true)
    {
        if (!$parse) return $this->document;

        if (!$this->isParsed() && !$this->parsing) {
            $this->parsing = true;

            // On Pre Parse Event
            if (is_callable($this->getOnPreParse())) {
                call_user_func_array($this->getOnPreParse(), array($this));
            }

            // Insert any body templates
            if ($this->body) {
                foreach ($this->bodyTemplates as $child) {
                    $this->appendTemplate($this->body, $child);
                }
            }

            /** @var \DOMElement $node */
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

            /* @var Repeat $repeat */
            foreach ($this->repeat as $name => $repeat) {
                $node = $repeat->getRepeatNode();
                if (!$node || !isset($node->parentNode) || !$node->parentNode) {
                    continue;
                }
                $node->parentNode->removeChild($node);
                unset($this->repeat[$name]);
            }

            // Remove nodes marked hidden
            foreach ($this->var as $var => $nodes) {
                /** @var \DOMElement $node */
                foreach ($nodes as $node) {
                    if (!$node || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_HIDDEN) && $node->getAttribute(self::ATTR_HIDDEN) == 'true') {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Remove choice node marked hidden
            foreach ($this->choice as $choice => $nodes) {
                /** @var \DOMElement $node */
                foreach ($nodes as $node) {
                    if (!$node || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_HIDDEN) && $node->getAttribute(self::ATTR_HIDDEN) == 'true') {
                        $node->parentNode->removeChild($node);
                    }
                }
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

            // On Post Parse Event
            if (is_callable($this->getOnPostParse())) {
                call_user_func_array($this->getOnPostParse(), array($this));
            }
            $this->parsing = false;
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
     * @param string $property (var, choice, repeat)
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
            $list1 = get_html_translation_table(HTML_ENTITIES, ENT_NOQUOTES);
            $list2 = get_html_translation_table(HTML_SPECIALCHARS, ENT_NOQUOTES);
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
        $xml = preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $xml);       // Strip out unsupported characters from XML
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


    // Alias functions

    /**
     * Add a css class if it does not exist
     *
     * @param string $var
     * @param string|array $class
     * @return Template
     * @notes Alias to Template::addClass()
     */
    public function addCss($var, $class)
    {
        return $this->addClass($var, $class);
    }

    /**
     * Remove the class if it exists
     *
     * @param string $var
     * @param string $class
     * @return Template
     * @notes Alias to Template::removeClass()
     */
    public function removeCss($var, $class)
    {
        return $this->removeClass($var, $class);
    }


    /**
     * @param string $msg
     */
    protected function logError($msg)
    {
        $this->errors[] = $msg;
        $this->getLog()->error($msg);
    }

    /**
     * @param string $msg
     */
    protected function logNotice($msg)
    {
        $this->getLog()->notice($msg);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLog()
    {
        if (self::$logger)
            return self::$logger;
        return new \Psr\Log\NullLogger();
    }






























    /**
     * @param string $var
     * @return bool
     * @deprecated Use has($var)
     * @remove 2.6.0
     */
    public function hasVar($var) { return $this->has($var); }

    /**
     * Set a choice node to become visible in a document.
     *
     * @param string $choice The name of the choice
     * @return Template
     * @deprecated Use the new show($var) and hide($var)
     * @remove 2.6.0
     */
    public function setChoice($choice)
    {
        if (empty($this->choice[$choice])) return $this;
        $nodes = $this->choice[$choice];
        foreach ($nodes as $node) {
            if ($node->hasAttribute(self::ATTR_HIDDEN))
                $node->removeAttribute(self::ATTR_HIDDEN);
        }
        return $this;
    }

    /**
     * Set a choice node to become invisible in a document.
     *
     * @param string $choice The name of the choice
     * @return Template
     * @deprecated Use the new show($var) and hide($var)
     * @remove 2.6.0
     */
    public function unsetChoice($choice)
    {
        if (empty($this->choice[$choice])) return $this;
        $nodes = $this->choice[$choice];
        foreach ($nodes as $node) {
            if ($node->hasAttribute(self::ATTR_HIDDEN))
                $node->removeAttribute(self::ATTR_HIDDEN);
        }
        return $this;
    }


    /**
     * Get the choice node list
     *
     * @return array
     * @deprecated This will no longer return any valid nodes. just an empty array
     * @remove 2.6.0
     */
    public function getChoiceList()
    {
        return $this->choice;
    }

    /**
     * @param null $var
     * @return mixed
     * @deprecated use getVar()
     * @remove 2.6.0
     */
    public function getVarList($var = null) { return $this->getVar($var); }

    /**
     * @param string $var
     * @return Template
     * @deprecated use removeVar()
     * @remove 2.6.0
     */
    public function removeVarElement($var) { return $this->removeVar($var); }

    /**
     * Return the HTML/XML contents of a var node.
     * If there are more than one node with the same var name
     * the first one is selected by default.
     * Use the $idx if there is more than one var block
     *
     * @param string $var
     * @param int $idx
     * @return string
     * @deprecated Use Template::getHtml()
     * @remove 2.6.0
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

    // ---------------- INSERT --------------------

    /**
     * Insert HTML formatted text into a var element.
     *
     * @param string $var
     * @param string $html
     * @return Template
     * @warn bug exists where after insertion the template loses
     *   reference to the node in repeat regions. The fix (for now)
     *   is to just do all operations on that var node before this call.
     * @deprecated Will be removed Use appendHtml()
     * @remove 2.6.0
     */
    public function insertHtml($var, $html)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        foreach ($nodes as $i => $node) {
            try {
                self::insertHtmlDom($node, $html, $this->encoding);
            } catch (\Exception $e) {
                $this->logError($e->__toString());
            }
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
     * @deprecated Will be removed Use appendHtmlDom()
     * @throws Exception
     * @remove 2.6.0
     */
    public static function insertHtmlDom($element, $html, $encoding = 'UTF-8')
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
     * Insert a DOMDocument into a var element
     * The var tag will not be replaced only its contents
     *
     * @param string $var
     * @param \DOMDocument $doc
     * @return Template
     * @deprecated Will be removed Use appendDoc()
     * @remove 2.6.0
     */
    public function insertDoc($var, \DOMDocument $doc)
    {
        if (!$this->isWritable('var', $var))
            return $this;
        $nodes = $this->findVar($var);
        /* @var \DOMElement $node */
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
     * @deprecated Will be removed Use appendTemplate()
     * @remove 2.6.0
     */
    public function insertTemplate($var, $template, $parse = true)
    {
        if (!$this->isWritable('var', $var) || !$template)
            return $this;
        $this->mergeTemplate($template);
        return $this->insertDoc($var, $template->getDocument($parse));
    }


    /**
     * Get a var element node from the document.
     *
     * @param string $var
     * @return \DOMElement[]|\DOMElement
     * @deprecated use getVar()
     * @remove 2.6.0
     */
    public function getVarElement($var)
    {
        $nodes = $this->findVar($var);
        if (is_array($nodes) && count($nodes)) {
            return $nodes[0];
        }
        return $nodes;
    }



}