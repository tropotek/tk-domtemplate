<?php
namespace Dom;

use Psr\Log\LoggerInterface;

/**
 * A PHP DOM Template Library
 *
 * NOTE: ATTR_ constants are considered reserved tag attributes and should
 *       not be used in any templates supplied to the Template for parsing
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Template
{
    /**
     * ATTR_ constants are considered reserved tag attributes and should
     * not be used in any templates supplied to the Template for parsing.
     */
    const ATTR_HIDDEN = '__tdt--hide';

    /**
     * This attribute will be added to script and style elements
     * to show the code location that the data was inserted from.
     */
    const ATTR_DATA_TRACE = 'data-trace';

    /**
     * All header nodes are deleted on parse
     * add this attribute to the header tag to force the template to ignore it.
     * Header nodes include <script>, <style>, <link> and <meta> (self::$HEADER_NODES)
     */
    const ATTR_HEAD_IGNORE = 'data-headParse';


    /**
     * Remove CDATA tags from output
     * For the toString() method
     */
    public static bool $REMOVE_CDATA = true;

    /**
     * These are the default attributes the DomTemplate uses for key nodes.
     * You can change these if they conflict with your template designs.
     */
    public static string $ATTR_VAR    = 'var';
    public static string $ATTR_CHOICE = 'choice';
    public static string $ATTR_REPEAT = 'repeat';

    public static array $HEADER_NODES = ['script', 'style', 'link', 'meta'];
    public static array $FORM_ELEMENT_NODES = ['input', 'textarea', 'select', 'button'];

    /**
     * Set the logger in your boostrap if you want to enable logging.
     *     \Dom\Template::$LOGGER = $factory->getLogger();
     */
    public static ?LoggerInterface $LOGGER = null;


    /**
     * Enable addition of data-tracer attributes to inserted JS and CSS
     * This will add an attribute (ATTR_DATA_TRACE) to the JS and CSS
     * tag showing where the script was added from
     */
    public static bool $ENABLE_TRACER = false;


    /**
     * Set to true if this template uses HTML5
     */
    private bool $html5 = false;

    /**
     * The character encoding use with this Template
     */
    private string $encoding = 'UTF-8';

    /**
     * This is the original string document sent to the template
     * before template initialisation
     */
    private string $html = '';

    /**
     * Cache the string state of this template when being serialized
     */
    private ?string $serialHtml = null;

    /**
     * Cache of the string document of the template after is has been parsed
     */
    private ?string $parsedXml = null;

    /**
     * The template document
     */
    protected ?\DOMDocument $document = null;

    /**
     * The original template document
     * before template initialisation
     */
    private ?\DOMDocument $orgDocument = null;

    /**
     * An array of var attr \DOMElement objects
     * @var array|\DOMElement[]
     */
    protected array $var = [];

    /**
     * This array stores all elements that are to be removed when parsed
     * @var array|\DOMElement[]
     */
    protected array $choice = [];

    /**
     * An array of repeat attr \DOMElement objects
     * @var array|\DOMElement[]
     */
    protected array $repeat = [];

    /**
     * An array of form DOMElement objects
     * @var array|\DOMElement[]
     */
    protected array $form = [];

    /**
     * An array of formElement DOMElement objects
     * @var array|\DOMElement[][]
     */
    protected array $formElement = [];

    /**
     * Track all id attribute nodes
     * @var array|\DOMElement[]
     */
    protected array $idList = [];

    /**
     * An internal list of nodes to delete after init()
     * @var array|\DOMNode[]
     */
    private array $delete = [];

    /**
     * Comment tags to be removed
     */
    protected array $comments = [];

    /**
     * The head tag of a html page
     */
    protected ?\DOMElement $head = null;

    /**
     * The body tag of a html page
     */
    protected ?\DOMElement $body = null;

    /**
     * The head tag of a html page
     */
    protected ?\DOMElement $title = null;

    /**
     * Headers to be created and appended to the <head> tag
     * on rendering of template
     * Holds arrays of headers descriptions in the format of:
     * [
     *   'elementName' => null,     // string
     *   'attributes' => null,      // string[]
     *   'value' => null,           // string
     *   'node' => null,            // (optional) \DOMElement to append to
     * ]
     */
    protected array $headers = [];

    /**
     * Templates to be appended to the <body> tag
     * on rendering of the template
     * @var array|Template[]
     */
    protected array $bodyTemplates = [];

    /**
     * An array of errors thrown
     */
    protected array $errors = [];

    /**
     * Blocking var to avoid a callback recursive loop
     */
    private bool $parsing = false;

    /**
     * Set to true if this template has been parsed
     */
    protected bool $parsed = false;

    /**
     * @var null|callable
     */
    protected $onPreParse = null;

    /**
     * @var null|callable
     */
    protected $onPostParse = null;


    public function __construct(\DOMDocument $doc, string $xml = '', string $encoding = 'UTF-8')
    {
        $this->html = $xml;
        $this->init($doc, $encoding);
    }

    /**
     * Make a template from a string
     * @throws Exception
     */
    public static function load(string $html, string $encoding = 'UTF-8'): Template
    {
        $html = trim($html);
        if ($html == '' || $html[0] != '<') {
            throw new Exception('Please supply a valid XHTML/XML string to create the DOMDocument.');
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);

        $html = self::cleanHtml($html, $encoding);
        $isHtml5 = false;
        if ('<!doctype html>' == strtolower(substr($html, 0, 15))) {
            $isHtml5 = true;
            $html = substr($html, 16);
        }
        //$ok = $doc->loadXML($xml);
        $ok = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$ok) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $str .= "\n\n" . \Tk\Str::lineNumbers($html) . "\n";
            $e = new Exception('Error Parsing DOM Template', 500, null, $str);
            throw $e;
        }

        $obj = new self($doc, $html, $encoding);
        $obj->html5 = $isHtml5;
        return $obj;
    }

    /**
     * Make a template from a file
     *
     * @throws Exception
     */
    public static function loadFile(string $filename, string $encoding = 'UTF-8'): Template
    {
        if (!is_file($filename)) {
            throw new Exception('Cannot locate file: ' . $filename);
        }
        $html = file_get_contents($filename);
        $obj = self::load($html, $encoding);
        $obj->document->documentURI = $filename;
        return $obj;
    }

    public function __sleep(): array
    {
        //$this->serialHtml = $this->document->saveXML();
        $this->serialHtml = $this->document->saveHTML();
        return array('html', 'serialHtml', 'encoding', 'headers', 'parsed');
    }

    public function __wakeup()
    {
        $doc = new \DOMDocument();
        //$doc->loadXML($this->serialHtml);
        $doc->loadHTML($this->serialHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->init($doc, $this->encoding);
    }

    public function __clone()
    {
        $this->init(clone $this->getOriginalDocument(), $this->encoding);
    }

    /**
     * Reset and prepare the template object.
     * Mainly used for the Repeat objects
     * but could be useful for your own methods.
     */
    public function init(\DOMDocument $doc, string $encoding = 'UTF-8'): Template
    {
        $this->document = $doc;
        $this->encoding = $encoding;
        $this->var = [];
        $this->choice = [];
        $this->repeat = [];
        $this->form = [];
        $this->formElement = [];
        $this->idList = [];
        $this->headers = [];
        $this->delete = [];
        $this->comments = [];
        $this->head = $this->body = $this->title = null;
        $this->parsed = false;
        $this->html5 = false;
        $this->orgDocument = clone $doc;
        if (!$this->html) {
            //$this->html = $this->document->saveXML();
            $this->html = $this->document->saveHTML();
        }

        $this->prepareDoc($this->document->documentElement);
        foreach ($this->delete as $node) {
            $node->parentNode->removeChild($node);
        }
        $this->delete = [];

        return $this;
    }

    /**
     * A private recursive method to initialise the template.
     */
    private function prepareDoc(\DOMNode $node, string $form = ''): void
    {
        if ($this->isParsed()) return;
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            /** @var $node \DOMElement */
            // Store all repeat regions
            if ($node->hasAttribute(self::$ATTR_REPEAT)) {
                $repeatName = $node->getAttribute(self::$ATTR_REPEAT);
                $this->repeat[$repeatName] = new Repeat($node, $this);
                $node->removeAttribute(self::$ATTR_REPEAT);
                return;
            }

            // Store all var nodes
            if ($node->hasAttribute(self::$ATTR_VAR)) {
                $varStr = $node->getAttribute(self::$ATTR_VAR);
                $arrAtts = explode(' ', $varStr);
                foreach ($arrAtts as $var) {
                    $this->var[$var][] = $node;
                    $node->removeAttribute(self::$ATTR_VAR);
                }
            }

            // Store all choice nodes
            if ($node->hasAttribute(self::$ATTR_CHOICE)) {
                $arrAtts = explode(' ', $node->getAttribute(self::$ATTR_CHOICE));
                foreach ($arrAtts as $choice) {
                    $this->choice[$choice][] = $node;
                    $this->var[$choice][] = $node;
                    $node->setAttribute(self::ATTR_HIDDEN, 'true');
                }
                $node->removeAttribute(self::$ATTR_CHOICE);
            }

            // Store all Id nodes.
            if ($node->hasAttribute('id')) {
                $this->idList[$node->getAttribute('id')] = $node;
            }

            // Store all Form nodes
            if ($node->nodeName == 'form') {
                $form = $node->getAttribute('id') ?? $node->getAttribute('name');
                if ($form == null) {
                    $form = count($this->formElement);
                }
                $this->formElement[$form] = [];
                $this->form[$form] = $node;
            }

            // Store all FormElement nodes
            if (in_array($node->nodeName, self::$FORM_ELEMENT_NODES)) {
                $id = $node->getAttribute('name');
                if ($id == null) {
                    $id = $node->getAttribute('id');
                }
                $this->formElement[$form][$id][] = $node;
                if (!isset($this->form[$form]) && $form == '') $this->form[$form] = $this->document->documentElement;
            }

            if ($node->nodeName == 'head') {
                $this->head = $node;
            }
            if ($node->nodeName == 'body') {
                $this->body = $node;
            }
            if ($node->nodeName == 'title' && $this->head) {
                $this->title = $node;
                return;
            }
            if (!$this->head) {
                // move all header nodes for compilation
                if (in_array($node->nodeName, self::$HEADER_NODES)) {
                    if ($node->hasAttribute(self::ATTR_HEAD_IGNORE)) return;
                    $attrs = [];
                    foreach ($node->attributes as $k => $v) {
                        if (in_array($k, [self::$ATTR_VAR, self::$ATTR_CHOICE, self::$ATTR_REPEAT]))
                            continue;
                        $attrs[$k] = $v->nodeValue;
                    }
                    $this->appendHeadElement($node->nodeName, $attrs, $node->textContent);
                    $this->delete[] = $node;
                    return;
                }
            }

            // iterate through the dom elements
            $children = $node->childNodes;
            foreach ($children as $child) {
                if ($child->nodeType == \XML_COMMENT_NODE) {
                    $this->comments[] = $child;
                }
                $this->prepareDoc($child, $form);
            }
        }

    }


    /**
     * Test if this template is HTML5 compliant
     * This only checks to see if the `<!doctype html>` tag exists at the start of the document
     */
    public function isHtml5(): bool
    {
        return $this->html5;
    }

    /**
     * Get the current DOMDocument character encoding
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Get the original text used to create this Template
     */
    public function getTemplateHtml(): string
    {
        return $this->html;
    }

    /**
     * Return a copy of the original \DOMDocument before the template ini
     */
    public function getOriginalDocument(): \DOMDocument
    {
        return $this->orgDocument;
    }

    /**
     * Reset the template to its unedited state
     */
    public function reset(): Template
    {
        $this->init($this->getOriginalDocument(), $this->getEncoding());
        return $this;
    }

    /**
     * Return the document file path if one exists.
     * For non file based templates this value will be the same as dirname($_SERVER['PHP_SELF'])
     */
    public function getTemplatePath(): string
    {
        return $this->document->documentURI;
    }

    /**
     * Return the title node if it exists.
     */
    public function getTitleElement(): ?\DOMElement
    {
        return $this->title;
    }

    /**
     * Return the head node if it exists.
     */
    public function getHeadElement(): ?\DOMElement
    {
        return $this->head;
    }

    /**
     * Return the current list of header nodes
     * Holds arrays of headers descriptions in the format of:
     * [
     *   'elementName' => null,     // string
     *   'attributes' => null,      // string[]
     *   'value' => null,           // string
     *   'node' => null,            // (optional) \DOMElement to append to
     * ]
     *
     */
    public function getHeaderList(): array
    {
        return $this->headers;
    }

    /**
     * Get a DOMElement from the document based on its unique ID
     * ID attributes should be unique for XHTML documents, multiple names
     * are ignored and only the first node found is returned.
     */
    public function getElementById(string $id): ?\DOMElement
    {
        return $this->idList[$id] ?? null;
    }

    /**
     * Return the root document node.
     * IE: DomDocument->documentElement
     */
    public function getRootElement(): \DOMElement
    {
        return $this->document->documentElement;
    }

    /**
     * Gets the page title note  text.
     */
    public function getTitleText(): string
    {
        return $this->title->nodeValue;
    }

    /**
     * Return the body node.
     */
    public function getBodyElement(): ?\DOMElement
    {
        return $this->body;
    }

    /**
     * Return the current list of header nodes
     *
     * @return array|Template[]
     */
    public function getBodyTemplateList(): array
    {
        return $this->bodyTemplates;
    }

    /**
     * Internal method to enable var to be a DOMElement or array of DOMElements...
     */
    public function getVarList(null|string|\DOMElement $var = null): array
    {
        if ($var === null) return $this->var;
        if ($var instanceof \DOMElement) return [$var];
        if ($this->keyExists(self::$ATTR_VAR, $var)) {
            return $this->var[$var];
        }
        return [];
    }

    /**
     * Get a single var element node from the document.
     * Only use this if there is only one element
     * with that var name. If more exists the first found is returned
     */
    public function getVar(string|\DOMElement $var): ?\DOMElement
    {
        $nodes = $this->getVarList($var);
        return $nodes[0] ?? null;
    }

    /**
     * Check if this document has a var node
     */
    public function hasVar(string|\DOMElement $var): bool
    {
        return (count($this->getVarList($var)) > 0);
    }

    /**
     * It is recommended to use hide($var) unless you specifically want to remove the node from the tree.
     */
    public function removeVar(string|\DOMElement $var): Template
    {
        foreach($this->getVarList($var) as $node) {
            $node->parentNode->removeChild($node);
        }
        return $this;
    }

    /**
     * Get the choice node list
     *
     * @return array|\DOMElement[]
     */
    public function getChoiceList(): array
    {
        return $this->choice;
    }

    /**
     * Show/Hide a choice or a var node
     */
    public function setVisible(string|\DOMElement $choice, bool $b = true): Template
    {
        $nodes = $this->getVarList($choice);
        if ($b) {
            foreach ($nodes as $node) $node->removeAttribute(self::ATTR_HIDDEN);
            if (!$this->keyExists(self::$ATTR_CHOICE, $choice)) return $this;
            $nodes = $this->choice[$choice];
            foreach ($nodes as $node) $node->removeAttribute(self::ATTR_HIDDEN);
        } else {
            foreach ($nodes as $node) $node->setAttribute(self::ATTR_HIDDEN, self::ATTR_HIDDEN);
            if (!$this->keyExists(self::$ATTR_CHOICE, $choice)) return $this;
            $nodes = $this->choice[$choice];
            foreach ($nodes as $node) $node->setAttribute(self::ATTR_HIDDEN, self::ATTR_HIDDEN);
        }
        return $this;
    }

    /**
     * Get a repeating region from a document.
     */
    public function getRepeat(string $repeat): ?Repeat
    {
        if ($this->keyExists(self::$ATTR_REPEAT, $repeat)) {
            $obj = $this->repeat[$repeat];
            return clone $obj;
        }
        return null;
    }

    /**
     * Get the repeat node list
     *
     * @return array|\DOMElement[]
     */
    public function getRepeatList(): array
    {
        return $this->repeat;
    }

    /**
     * Check if a repeat,choice,var,form (template property) Exists.
     */
    public function keyExists(string $property, string $key): bool
    {
        return array_key_exists($key, $this->$property);
    }


    // -------------- Document Modifier code ---------------


    /**
     *  Find a node by its var/choice/repeat name
     *
     * @param string $attr [var, choice, repeat]
     */
    public static function findNodeByAttr(\DOMElement $node, string $value, string $attr): ?\DOMElement
    {
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->getAttribute($attr) == $value) {
                return $node;
            }
            // iterate through the children
            foreach ($node->childNodes as $child) {
                $found = self::findNodeByAttr($child, $value, $attr);
                if ($found) return $found;
            }
        }
        return null;
    }

    public function addCss(string|\DOMElement $var, array|string $class): Template
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
        if ($classStr)
            $this->setAttr($var, 'class', $classStr);
        return $this;
    }

    public function removeCss(string|\DOMElement $var, string $class): Template
    {
        $str = $this->getAttr($var, 'class');
        $str = preg_replace('/(' . $class . ')\s?/', '', trim($str));
        $this->setAttr($var, 'class', $str);
        return $this;
    }

    public function setAttr(string|\DOMElement $var, array|string $attr, ?string $value = null): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        if (!is_array($attr)) $attr = [$attr => $value];
        $nodes = $this->getVarList($var);
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

    public function getAttr(string|\DOMElement $var, string $attr): string
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return '';
        $nodes = $this->getVarList($var);
        if (count($nodes)) {
            return $nodes[0]->getAttribute($attr);
        }
        return '';
    }

    public function removeAttr(string|\DOMElement $var, string $attr): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $n) {
            $n->removeAttribute($attr);
        }
        return $this;
    }


    /**
     * Return a form object from the document.
     */
    public function getForm(string $id = ''): ?Form
    {
        if (!$this->isParsed() && isset($this->form[$id])) {
            return new Form($this->form[$id], $this->formElement[$id], $this);
        }
        return null;
    }

    /**
     * Sets the document title text if available.
     */
    public function setTitleText(string $value): Template
    {
        if (!$this->isParsed()) {
            if ($this->title == null) {
                $this->getLogger()->notice(__CLASS__.'::setTitleText() This document has no title node.');
                return $this;
            }
            $this->removeChildren($this->title);
            $this->title->nodeValue = htmlentities(html_entity_decode($value));
        }
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
     * If this template does not have a <head> tag the elements will be added to
     * any parent templates that this template is appended/inserted/prepended etc to.
     *
     * @param array $attributes An associative array of (attr, value) pairs.
     * @param \DOMElement|null $node (optional) If sent this head element will append after the supplied node
     */
    public function appendHeadElement(string $elementName, array $attributes, string $value = '', ?\DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        $preKey = $elementName . $value;
        $ignore = array('content', 'type', self::ATTR_DATA_TRACE);
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
     * @param \DOMElement|null $node (optional) If sent this head element will append after the supplied node
     */
    public function appendMetaTag(string $name, string $content, ?\DOMElement $node = null): Template
    {
        return $this->appendHeadElement('meta', array('name' => $name, 'content' => $content), '', $node);
    }

    /**
     * Append a stylesheet file to the template header
     *
     * @param \DOMElement|null $node (optional) If sent this head element will append after the supplied node
     */
    public function appendCssUrl(string $styleUrl, array $attrs = [], ?\DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        $attrs['rel'] = 'stylesheet';
        $attrs['href'] = $styleUrl;
        $this->addTracer($attrs);
        $this->appendHeadElement('link', $attrs, '', $node);
        return $this;
    }

    /**
     * Append some styles to the template header in a <style> element
     *
     * @param \DOMElement|null $node (optional) If sent this head element will append after the supplied node
     */
    public function appendCss(string $styles, array $attrs = [], ?\DOMElement $node = null): Template
    {
        if (!trim($styles) || $this->isParsed()) return $this;
        $this->addTracer($attrs);
        $this->appendHeadElement('style', $attrs, "\n" . $styles . "\n", $node);
        return $this;
    }

    /**
     * Append a Javascript file to the template header
     *
     * @param \DOMElement|null $node (optional) If sent this head element will append after the supplied node
     */
    public function appendJsUrl(string $urlString, array $attrs = [], ?\DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        if (!isset($attrs['type']) && !$this->isHtml5()) {
            $attrs['type'] = 'text/javascript';
        }
        if (!isset($attrs['src'])) {
            $attrs['src'] = $urlString;
        }
        $this->addTracer($attrs);
        $this->appendHeadElement('script', $attrs, '', $node);
        return $this;
    }

    /**
     * Append some Javascript to the template header in a <script> element
     *
     * @param \DOMElement|null $node (optional) If supplied, this element will append after the supplied node
     */
    public function appendJs(string $js, array $attrs = [], ?\DOMElement $node = null): Template
    {
        if (!trim($js) || $this->isParsed()) return $this;
        $this->addTracer($attrs);
        $this->appendHeadElement('script', $attrs, $js, $node);
        return $this;
    }

    /**
     * Add the calling trace to a notes attributes
     * @todo: see if this works ok in a class env
     */
    private function addTracer(array &$attrs): void
    {
        $trace = debug_backtrace();

        $i = 2;
        if (self::$ENABLE_TRACER && !empty($trace[$i]) && empty($attrs[self::ATTR_DATA_TRACE])) {
            $attrs[self::ATTR_DATA_TRACE] = (!empty($trace[$i]['class']) ? $trace[$i]['class'] . '::' : '').(!empty($trace[$i]['function']) ? $trace[$i]['function'] . '()' : '');
        }
    }

    /**
     * Append a template to the <body> tag, the supplied template
     * will be merged into other templates until a <body> tag exists
     */
    public function appendBodyTemplate(Template $template): Template
    {
        if ($this->isParsed()) return $this;
        $this->bodyTemplates[] = $template;
        return $this;
    }

    /**
     * Merging a template copies all the headers and bodyTemplate
     * from the $srcTemplate to this template
     */
    protected function mergeTemplate(Template $srcTemplate): Template
    {
        if ($this->isParsed()) return $this;
        $this->appendHeaderList($srcTemplate->getHeaderList());
        $this->appendBodyTemplateList($srcTemplate->getBodyTemplateList());
        return $this;
    }

    /**
     * Merge existing header array with this template header array
     *
     * @param array|Template[] $arr
     */
    public function appendBodyTemplateList(array $arr): Template
    {
        if ($this->isParsed()) return $this;
        $this->bodyTemplates = array_merge($this->bodyTemplates, $arr);
        return $this;
    }

    /**
     * merge existing header array with this template header array
     */
    public function appendHeaderList(array $arr): Template
    {
        if ($this->isParsed()) return $this;
        $this->headers = array_merge($this->headers, $arr);
        return $this;
    }

    /**
     * Remove all child nodes from a var
     */
    public function empty(string|\DOMElement $var): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            $this->removeChildren($node);
        }
        return $this;
    }


    /**
     * Get the text inside a var node.
     */
    public function getText(string|\DOMElement $var): string
    {
        $nodes = $this->getVarList($var);
        if (count($nodes)) {
            return $nodes[0]->textContent;
        }
        return '';
    }

    /**
     * Replace the text of a var element
     */
    public function setText(string|\DOMElement $var, string $value): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            $this->removeChildren($node);
            $newNode = $this->document->createTextNode($value);
            $node->appendChild($newNode);
        }
        return $this;
    }

    /**
     * Append text to a var element
     */
    public function appendText(string|\DOMElement $var, string $value): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            $newNode = $this->document->createTextNode($value);
            $node->appendChild($newNode);
        }
        return $this;
    }

    /**
     * Prepend text to a var element
     */
    public function prependText(string|\DOMElement $var, string $value): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            $newNode = $this->document->createTextNode($value);
            $node->insertBefore($newNode, $node->firstChild);
        }
        return $this;
    }


    /**
     * Return the html including the node contents
     */
    public function getHtml(string|\DOMElement $var): string
    {
        $html = '';
        $nodes = $this->getVarList($var);
        if (count($nodes)) {
            $doc = new \DOMDocument();
            $doc->appendChild($doc->importNode($nodes[0], true));
            $html = trim($doc->saveHTML());
        }
        return $html;
    }

    /**
     * Replace a template var element with the supplied HTML
     *
     * @param bool $preserveRootAttr Copy existing attributes of destination element to new node
     * @note Make sure you have a root node surrounding the content eg: `<p>content ...</p>`
     */
    public function replaceHtml(string|\DOMNode $var, string $html, bool $preserveRootAttr = true): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        $this->empty($var);
        foreach ($nodes as $i => $node) {
            try {
                $newNode = self::replaceDomHtml($node, $html, $this->encoding, $preserveRootAttr);
                if ($newNode) {
                    $this->var[$var][$i] = $newNode;
                }
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Insert HTML formatted text into a var element.
     *
     * @note After insertion the template will lose
     *   reference to any contained repeat element nodes. The fix
     *   is to just do all operations on the repeat templates/elements before this call.
     */
    public function insertHtml(string|\DOMElement $var, string $html): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            try {
                self::insertDomHtml($node, $html, $this->encoding);
            } catch (\Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML into a var element
     */
    public function appendHtml(string|\DOMElement $var, string $html): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            try {
                self::appendDomHtml($node, $html, $this->encoding);
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML into a var element
     */
    public function prependHtml(string|\DOMElement $var, string $html): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
        foreach ($nodes as $node) {
            try {
                self::prependDomHtml($node, $html);
            } catch (Exception $e) {
                $this->logError($e->__toString());
            }
        }
        return $this;
    }


    /**
     * Replace HTML on a dom node
     * This will replace the existing node not just its inner contents.
     *
     * @param bool $preserveRootAttr Copy existing attributes of destination element to new node
     * @throws Exception
     * @note Make sure you have a root node surrounding the content eg: `<p>content ...</p>`
     */
    public static function replaceDomHtml(\DOMNode $element, string $html, string $encoding = 'UTF-8', bool $preserveRootAttr = true): ?\DOMNode
    {
        if (!$html) return null;

        $html = self::cleanHtml($html, $encoding);
        if (str_starts_with($html, '<?xml')) {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }
        $elementDoc = $element->ownerDocument;

        $contentNode = self::makeContentNode($html);
        $contentNode = $contentNode->firstChild;
        $contentNode = $elementDoc->importNode($contentNode, true);
        if ($element->hasAttributes() && $preserveRootAttr && $contentNode->nodeType == \XML_ELEMENT_NODE) {
            foreach ($element->attributes as $attr) {
                $contentNode->setAttribute($attr->nodeName, $attr->nodeValue);
            }
        }
        $element->parentNode->replaceChild($contentNode, $element);
        return $contentNode;
    }

    /**
     * Insert HTML formatted text into a dom element.
     *
     * @throws Exception
     */
    public static function insertDomHtml(\DOMNode $element, string $html, string $encoding = 'UTF-8'): ?\DOMNode
    {
        if ($html == null) return null;

        $elementDoc = $element->ownerDocument;
        while ($element->hasChildNodes()) {
            $element->removeChild($element->childNodes->item(0));
        }
        $html = self::cleanHtml($html, $encoding);
        if (str_starts_with($html, '<?xml')) {
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
     * Append HTML text into a dom node.
     *
     * @throws Exception
     */
    public static function appendDomHtml(\DOMNode $element, string $html, string $encoding = 'UTF-8'): ?\DOMNode
    {
        if (!$html) return null;

        $html = self::cleanHtml($html, $encoding);
        if (str_starts_with($html, '<?xml')) {
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
     * Append HTML text into a dom node.
     *
     * @throws Exception
     */
    public static function prependDomHtml(\DOMNode $element, string $html): ?\DOMNode
    {
        if (!$html) return null;

        $elementDoc = $element->ownerDocument;
        $contentNode = self::makeContentNode($html);
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
     * Replace a var element with a DOMDocument contents
     *
     * The DOMDocument's topmost node will be used to replace the destination node
     * This will replace the existing node not just its inner contents
     *
     * @param bool $preserveRootAttr Copy existing attributes of destination element to new node
     * @note Make sure you have a root node surrounding the content eg: `<p>content ...</p>`
     */
    public function replaceDocHtml(string|\DOMElement $var, \DOMDocument $doc, bool $preserveRootAttr = true): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        if (!$doc->documentElement) return $this;

        $nodes = $this->getVarList($var);
        foreach ($nodes as $i => $node) {
            $newNode = $this->document->importNode($doc->documentElement, true);
            if ($node->hasAttributes() && $preserveRootAttr && $newNode->nodeType == \XML_ELEMENT_NODE) {
                foreach ($node->attributes as $attr) {
                    $newNode->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }
            $node->parentNode->replaceChild($newNode, $node);
            if (is_string($var)) {
                $this->var[$var][$i] = $newNode;
            }
        }
        return $this;
    }

    /**
     * Insert a DOMDocument into a var element
     * The var tag will not be replaced only its contents
     */
    public function insertDocHtml(string|\DOMElement $var, \DOMDocument $doc): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $nodes = $this->getVarList($var);
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
     * Append a var element with a DOMDocument contents
     */
    public function appendDocHtml(string|\DOMElement $var, \DOMDocument $doc): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        if (!$doc->documentElement) return $this;

        $nodes = $this->getVarList($var);
        foreach ($nodes as $el) {
            $node = $this->document->importNode($doc->documentElement, true);
            $el->appendChild($node);
        }
        return $this;
    }

    /**
     * Prepend documents to the var node
     */
    public function prependDocHtml(string|\DOMElement $var, \DOMDocument $doc): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        if (!$doc->documentElement) return $this;

        $nodes = $this->getVarList($var);
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
     * Parse and Insert a template into a var element
     * The var tag will not be replaced only its contents
     *
     * This will also grab any headers in the supplied template.
     *
     * @throws \DOMException
     */
    public function insertTemplate(string|\DOMElement $var, Template $template): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $this->mergeTemplate($template);
        return $this->insertDocHtml($var, $template->getDocument());
    }

    /**
     * Replace a var node with the supplied Template
     * The DOMDocument's topmost node will be used to replace the destination node
     *
     * This will also copy any headers in the supplied template.
     * This will replace the existing node not just its inner contents
     *
     * @param bool $preserveRootAttr Copy existing attributes of destination element to new node
     * @throws \DOMException
     * @note Make sure you have a root node surrounding the content eg: `<p>content ...</p>`
     */
    public function replaceTemplate(string|\DOMElement $var, Template $template, bool $preserveRootAttr = true): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $this->mergeTemplate($template);
        return $this->replaceDocHtml($var, $template->getDocument(), $preserveRootAttr);
    }

    /**
     * Append a template to a var element, it will parse the template before appending it
     * This will also copy any headers in the $template.
     *
     * @throws \DOMException
     */
    public function appendTemplate(string|\DOMElement $var, Template $template): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $this->mergeTemplate($template);
        return $this->appendDocHtml($var, $template->getDocument());
    }

    /**
     * Prepend a template to a var element, it will parse the template before appending it
     * This will also copy any headers in the $template.
     *
     * @throws \DOMException
     */
    public function prependTemplate(string|\DOMElement $var, Template $template): Template
    {
        if (!$this->isWritable(self::$ATTR_VAR, $var)) return $this;
        $this->mergeTemplate($template);
        return $this->prependDocHtml($var, $template->getDocument());
    }


    /**
     * Prepare XML/HTML markup string ready for insertion into a node.
     *
     * Some methods require that there be a start and end tax before a node can be inserted.
     * This method fixes that issue.
     *
     * @throws Exception
     */
    public static function makeContentNode(string $markup, string $encoding = 'UTF-8'): \DOMNode
    {
        $markup = self::cleanHtml($markup, $encoding);
        $id = '_c_o_n__';
        //$html = sprintf('<?xml version="1.0" encoding="%s" ? ><div xml:id="%s">%s</div>', $encoding, $id, $markup);
        $html = sprintf('<div id="%s">%s</div>', $id, $markup);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        //$ok = $doc->loadXML($html);
        $ok = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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
     * Removes all children from a node.
     */
    protected function removeChildren(\DOMNode $node): Template
    {
        while ($node->hasChildNodes()) {
            $node->removeChild($node->childNodes->item(0));
        }
        return $this;
    }

    /**
     * Check if a repeat,choice,var,form (template property) exist,
     * and if the document has been parsed.
     *
     * @param string $property [var, choice, repeat]
     */
    public function isWritable(string $property, string|\DOMElement $key): bool
    {
        if ($this->isParsed()) return false;

        if ($property && is_string($key)) {
            if (!$this->keyExists($property, $key))
                return false;
        }
        return true;
    }

    /**
     * Get the parsed state of the template.
     * If true then no more changes can be made to the template
     * because the template has already been parsed.
     */
    public function isParsed(): bool
    {
        return $this->parsed;
    }

    /**
     * Add a callable function on pre document parsing
     *
     * EG: $template->setOnPreParse(function ($template) { });
     */
    public function setOnPreParse(callable $onPreParse): Template
    {
        $this->onPreParse = $onPreParse;
        return $this;
    }

    /**
     * Add a callable function on post document parsing
     *
     * EG: $template->setOnPostParse(function ($template) { });
     */
    public function setOnPostParse(callable $onPostParse): Template
    {
        $this->onPostParse = $onPostParse;
        return $this;
    }

    /**
     * Return a parsed \Dom document.
     *
     * After using this call ($parse = true) you can no longer use the template render functions
     * as no changes can be made to the template unless you use DOMDocument functions directly
     * @throws \DOMException
     */
    public function getDocument(bool $parse = true): ?\DOMDocument
    {
        if (!$parse) return $this->document;

        if (!$this->isParsed() && !$this->parsing) {
            $this->parsing = true;

            // Call Pre Parse Event
            if (is_callable($this->onPreParse)) {
                call_user_func_array($this->onPreParse, [$this]);
            }

            // Insert body templates
            if ($this->body) {
                foreach ($this->bodyTemplates as $child) {
                    $this->appendTemplate($this->body, $child);
                }
            }

            // Remove comments if not used
            foreach ($this->comments as $node) {
                if (!$node || !isset($node->parentNode) || !$node->parentNode || !$node->ownerDocument ) {
                    continue;
                }
                // Keep the IE comment control statements
                if ($node->nodeName == null || preg_match('/^\[if /', $node->nodeValue)) {
                    continue;
                }
                if ($node->parentNode->nodeName != 'script' && $node->parentNode->nodeName != 'style') {
                    $node->parentNode->removeChild($node);
                }
            }

            // Remove repeat template notes
            foreach ($this->repeat as $name => $repeat) {
                $node = $repeat->getRepeatNode();
                if (!$node instanceof \DOMElement || !isset($node->parentNode) || !$node->parentNode) {
                    continue;
                }
                $node->parentNode->removeChild($node);
                unset($this->repeat[$name]);
            }

            // Remove nodes marked hidden
            foreach ($this->var as $var => $nodes) {
                foreach ($nodes as $node) {
                    if (!$node instanceof \DOMElement || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_HIDDEN)) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Remove choice node marked hidden
            foreach ($this->choice as $choice => $nodes) {
                foreach ($nodes as $node) {
                    if (!$node instanceof \DOMElement || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_HIDDEN)) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Insert headers
            if ($this->head) {
                $meta = [];
                $other = [];
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
                    $t = $this->document->createTextNode("  ");
                    if ($header['node']) {
                        $n = $header['node'];
                        $n->parentNode->insertBefore($node, $n);
                        $n->parentNode->insertBefore($nl, $n);
                    } else {
                        if ($this->title) {
                            $this->head->insertBefore($node, $this->title);
                            $this->head->insertBefore($nl, $this->title);
                            $this->head->insertBefore($t, $this->title);
                        } else {
                            $this->head->insertBefore($node, $this->head->firstChild);
                            $this->head->insertBefore($t, $this->head->firstChild);
                            $this->head->insertBefore($nl, $this->head->firstChild);
                        }
                    }
                }
            }

            $this->parsed = true;
            $this->document->formatOutput = true;
            $this->document->preserveWhiteSpace = false;
            $this->document->normalizeDocument();

            // On Post Parse Event
            if (is_callable($this->onPostParse)) {
                call_user_func_array($this->onPostParse, [$this]);
            }
            $this->parsing = false;
        }

        $this->document->normalizeDocument();
        return $this->document;
    }

    /**
     * Return the document as an XML/XHTML string
     */
    public function toString(bool $parse = true): string
    {
        $str = '';
        try {
            $doc = $this->getDocument($parse);
            //$str = $doc->saveXML($doc->documentElement);
            $str = $doc->saveHTML($doc->documentElement);

            // Cleanup Document
            if (substr($str, 0, 5) == '<' . '?xml') {    // Remove xml declaration
                $str = substr($str, strpos($str, "\n") + 1);
            }
            if ($this->html5 && strtolower(substr($str, 0, 15)) != '<!doctype html>') {
                $str = "<!doctype html>\n" . $str;
            }

            // fix allowable non-closeable tags
            $str = preg_replace_callback('#<(\w+)([^>]*)\s*/>#s',
                function ($m) {
                    $xhtml_tags = array("br", "hr", "input", "frame", "img", "area", "link", "col", "base", "basefont", "param", "meta");
                    return in_array($m[1], $xhtml_tags) ? "<$m[1]$m[2] />" : "<$m[1]$m[2]></$m[1]>";
                }, $str);

            if (self::$REMOVE_CDATA) {
                $str = preg_replace('~<!\[CDATA\[\s*|\s*\]\]>~', '', $str);
            }

        } catch (\Exception $e) {
            $this->logError($e->__toString());
        }
        return $str;
    }

    /**
     * Return a string representation of this object
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Get the xml/html and return the cleaned string
     * A good place to clean any nasty html entities and other non-valid XML/XHTML elements
     */
    static function cleanHtml(string $xml, string $encoding = 'UTF-8'): string
    {
        static $mapping = null;
        if (!$mapping) {
            $list1 = get_html_translation_table(HTML_ENTITIES, ENT_NOQUOTES);
            $list2 = get_html_translation_table(HTML_SPECIALCHARS, ENT_NOQUOTES);
            $list = array_merge($list1, $list2);
            $mapping = [];
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
     * Here is a workaround.
     */
    static private function ord(string $ch): int
    {
        $k = mb_convert_encoding($ch, 'UCS-2LE', 'UTF-8');
        $k1 = ord(substr($k, 0, 1));
        $k2 = ord(substr($k, 1, 1));
        return $k2 * 256 + $k1;
    }

    protected function logError(string $msg): void
    {
        $this->errors[] = $msg;
        $this->getLogger()->error($msg);
    }

    protected function getLogger(): LoggerInterface
    {
        if (!self::$LOGGER) {
            self::$LOGGER = new \Psr\Log\NullLogger();
        }
        return self::$LOGGER;
    }

}