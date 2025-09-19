<?php
namespace Dom;

use Dom\Parser\ParserInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use Tk\Log;

/**
 * A PHP DOM Template Library
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Template
{
    /**
     * The internal template attribute to indicate a node to remove on parsing
     */
    const string ATTR_DELETE = 'data__delete';

    /**
     * The tracer attribute that will show the template source location for its value
     */
    const string ATTR_DATA_TRACE = 'data-dt-trace';

    // Types are used to identify the types in the nodeList
    const string TYPE_VAR          = 'var';
    const string TYPE_CHOICE       = 'choice';
    const string TYPE_REPEAT       = 'repeat';
    const string TYPE_ID           = 'id';
    const string TYPE_FORM         = 'form';
    const string TYPE_FORM_ELEMENT = 'form-element';

    /**
     * Enable the addition of data-tracer attributes to inserted JS and CSS
     * This will add an attribute (self::ATTR_DATA_TRACE) to the JS and CSS
     * tags showing where the script was added from.
     * Useful for debugging.
     */
    public static bool $ENABLE_TRACER = false;

    /**
     * These are the default attributes the DomTemplate uses for key nodes.
     * You can change these if they conflict with your template designs.
     */
    public static string $ATTR_VAR    = 'var';
    public static string $ATTR_CHOICE = 'choice';
    public static string $ATTR_REPEAT = 'repeat';

    public static array $FORM_ELEMENT_NODES = ['input', 'textarea', 'select', 'button'];

    /**
     * Parsers that are instantiated when a Template is constructed.
     * Recommended to keep these to a minimum.
     * Use DOM Modifiers where possible.
     *
     * @var array<int,string>
     */
    protected static array $TEMPLATE_PARSERS = [];

    /**
     * Set to true if this template uses HTML5
     */
    protected bool $html5 = false;

    /**
     * The character encoding use with this Template
     */
    protected string $encoding = 'UTF-8';

    /**
     * Cached for template when being serialized
     */
    private ?string $serialHtml = null;

    /**
     * Original HTML document
     */
    protected string $html = '';

    /**
     * The original template document before template initialization
     */
    protected ?DOMDocument $orgDocument = null;

    /**
     * The DOM template document created from the source HTML
     */
    protected ?DOMDocument $document = null;

    /**
     * Headers to be created and appended to the <head> tag
     * on parsing the template
     *
     * Holds arrays of header descriptions in the format of:
     * [
     *   'elementName' => '',
     *   'attributes' => ['attr' => 'val', ...],
     *   'value' => '',
     *   'node' => null, // (optional) \DOMElement to append to
     * ]
     * @var array<int,mixed>
     */
    protected array $headers = [];

    /**
     * Templates to be appended to the <body> tag on parsing the template
     * @var array<int,Template>
     */
    protected array $bodyTemplates = [];

    /**
     * The head tag of the template if exists
     */
    protected ?DOMElement $head = null;

    /**
     * The title tag of the template if exists
     */
    protected ?DOMElement $title = null;

    /**
     * The body tag of the template if exists
     */
    protected ?DOMElement $body = null;

    /**
     * @var array<int,ParserInterface>
     */
    protected array $parsers = [];

    /**
     * Blocking var to avoid a callback recursive loop
     */
    protected bool $parsing = false;

    /**
     * Set to true if this template has been parsed
     */
    protected bool $parsed = false;



    // TODO: see if we can clean this up a bit

    /**
     * @var null|callable
     */
    protected $onPreParse = null;

    /**
     * @var null|callable
     */
    protected $onPostParse = null;


    /**
     * The template node list for all parsable nodes
     * @var array<string,array<string,mixed>>
     */
    protected array $nodeList = [
        self::TYPE_VAR => [],
        self::TYPE_CHOICE => [],
        self::TYPE_REPEAT => [],
        self::TYPE_FORM => [],
        self::TYPE_FORM_ELEMENT => [],
        self::TYPE_ID => [],
    ];

    /**
     * @var array<string,array<int,DOMElement>>
     */
    protected array $var = [];

    /**
     * @var array<string,array<int,DOMElement>>
     */
    protected array $choice = [];

    /**
     * @var array<string,Repeat>
     */
    protected array $repeat = [];

    /**
     * @var array<string,DOMElement>
     */
    protected array $form = [];

    /**
     * @var array<string,array<string,array<int,DOMElement>>>
     */
    protected array $formElement = [];

    /**
     * @var array<string,DOMElement>
     */
    protected array $idList = [];



    public function __construct(DOMDocument $doc, string $xml = '', string $encoding = 'UTF-8')
    {
        $this->html = $xml;
        $this->reset($doc, $encoding);
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

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        $isHtml5 = false;
        if ('<!doctype html>' == strtolower(substr($html, 0, 15))) {
            $isHtml5 = true;
            $html = substr($html, 16);
        }
        $ok = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$ok) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            // add line numbers to the error message
            $lines = explode("\n", $html);
            foreach ($lines as $i => $line) {
                $lines[$i] = ($i+1) . '  ' . $line;
            }
            $str .= "\n\n" . implode("\n", $lines) . "\n";
            throw new Exception('Error Parsing DOM Template', 500, null, $str);
        }

        $obj = new self($doc, $html, $encoding);
        $obj->html5 = $isHtml5;
        return $obj;
    }

    /**
     * Make a template from a file
     */
    public static function loadFile(string $filename, string $encoding = 'UTF-8'): Template
    {
        $html = file_get_contents($filename);
        if ($html === false) {
            throw new Exception('Cannot locate file: ' . $filename);
        }
        $obj = self::load($html, $encoding);
        $obj->document->documentURI = $filename;
        return $obj;
    }

    public function __sleep(): array
    {
        $this->serialHtml = strval($this->document->saveHTML());
        return array('html', 'serialHtml', 'encoding', 'headers', 'parsed');
    }

    public function __wakeup()
    {
        $doc = new DOMDocument();
        $doc->loadHTML($this->serialHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->reset($doc, $this->encoding);
    }

    public function __clone()
    {
        $this->reset(clone $this->getOriginalDocument(), $this->encoding);
    }

    /**
     * Reset the template to its unedited state
     */
    public function init(?DOMDocument $doc = null, ?string $encoding = null): Template
    {
        $this->reset($this->getOriginalDocument(), $this->getEncoding());
        return $this;
    }

    /**
     * Reset and prepare the template object for use.
     * Can be called after parsing a template to reset the template
     * to its original state.
     */
    public function reset(?DOMDocument $doc = null, ?string $encoding = null): Template
    {
        $doc = $doc ?? $this->getOriginalDocument();
        $encoding = $encoding ?? $this->getEncoding();

        $this->document = $doc;
        $this->encoding = $encoding;
        $this->parsed = false;
        $this->html5 = false;
        $this->head = $this->body = $this->title = null;
        $this->nodeList = [
            self::TYPE_VAR => [],
            self::TYPE_CHOICE => [],
            self::TYPE_REPEAT => [],
            self::TYPE_FORM => [],
            self::TYPE_FORM_ELEMENT => [],
            self::TYPE_ID => [],
        ];

        $this->var = [];
        $this->choice = [];
        $this->repeat = [];
        $this->form = [];
        $this->formElement = [];
        $this->idList = [];

        $this->headers = [];
        $this->orgDocument = clone $doc;
        if (!$this->html) {
            $this->html = strval($this->document->saveHTML());
        }

        if (!empty(self::$TEMPLATE_PARSERS) && empty($this->parsers)) {
            foreach (self::$TEMPLATE_PARSERS as $parser) {
                $this->parsers[$parser] = new $parser($this);
            }
        }

        $this->prepareDoc($this->document->documentElement);

        return $this;
    }

    /**
     * A recursive method to initialize the template.
     */
    protected function prepareDoc(DOMNode $node, string $form = ''): void
    {
        if ($this->isParsed()) return;
        if ($node instanceof DOMElement) {
            // Store all repeat regions
            if ($node->hasAttribute(self::$ATTR_REPEAT)) {
                $repeatName = $node->getAttribute(self::$ATTR_REPEAT);
                $this->repeat[$repeatName] = new Repeat($node, $this);
                // TODO
                $this->addNode(self::TYPE_REPEAT, $repeatName, new Repeat($node, $this));
                $node->removeAttribute(self::$ATTR_REPEAT);
                return;
            }

            // Store all var nodes
            if ($node->hasAttribute(self::$ATTR_VAR)) {
                $varStr = $node->getAttribute(self::$ATTR_VAR);
                $arrAttrs = explode(' ', $varStr);
                foreach ($arrAttrs as $var) {
                    $this->var[$var][] = $node;
                    // TODO
                    $this->addNode(self::TYPE_VAR, $var, $node);
                    $node->removeAttribute(self::$ATTR_VAR);
                }
            }

            // Store all choice nodes
            if ($node->hasAttribute(self::$ATTR_CHOICE)) {
                $arrAttrs = explode(' ', $node->getAttribute(self::$ATTR_CHOICE));
                foreach ($arrAttrs as $choice) {
                    $this->choice[$choice][] = $node;
                    $this->var[$choice][] = $node;
                    // TODO
                    $this->addNode(self::TYPE_VAR, $choice, $node);
                    $this->addNode(self::TYPE_CHOICE, $choice, $node);
                    $node->setAttribute(self::ATTR_DELETE, 'true');
                }
                $node->removeAttribute(self::$ATTR_CHOICE);
            }

            // Store all Id nodes.
            if ($node->hasAttribute('id')) {
                $this->idList[$node->getAttribute('id')] = $node;
                // TODO
                $this->addNode(self::TYPE_ID, $node->getAttribute('id'), $node);
            }

            // Store all Form nodes
            if ($node->nodeName == 'form') {
                $form = strval($node->getAttribute('id') ?? $node->getAttribute('name') ?? '');
                if (!$form) {
                    $form = 'form-' . count($this->formElement);
                }
                $this->formElement[$form] = [];
                $this->form[$form] = $node;
                // TODO
                $this->addNode(self::TYPE_FORM, $form, $node);
            }

            // Store all FormElement nodes
            if (in_array($node->nodeName, self::$FORM_ELEMENT_NODES)) {
                $id = $node->getAttribute('name');
                if ($id == null) {
                    $id = $node->getAttribute('id');
                }

                $this->formElement[$form][$id][] = $node;
                if (!isset($this->form[$form]) && $form == '') $this->form[$form] = $this->document->documentElement;

                // TODO we should be using name here not id, fix this when moving to a Parser
                $this->addNode(self::TYPE_FORM_ELEMENT, $form.'-'.$id, $node);
                if (!$this->hasNode(self::TYPE_FORM, $form)) {
                    $this->addNode(self::TYPE_FORM, $form, $this->document->documentElement);
                }
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

            // iterate through the dom elements
            foreach ($node->childNodes as $child) {
                $this->prepareDoc($child, $form);
            }
        }
    }


    /**
     * Return a parsed \Dom document.
     *
     * After using this call ($parse = true) you can no longer use the template render functions
     * as no changes can be made to the template unless you use DOMDocument functions directly
     */
    public function parseDoc(): ?DOMDocument
    {
        if ($this->isParsed()) return $this->document;

        if (!$this->parsing) {
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

            // Remove repeat template notes
            foreach ($this->getNodeList(self::TYPE_REPEAT) as $name => $nodes) {
                foreach ($nodes as $repeat) {
                    $node = $repeat->getRepeatNode();
                    if (!$node instanceof DOMElement || !isset($node->parentNode) || !$node->parentNode) {
                        continue;
                    }
                    $node->parentNode->removeChild($node);
                }
                unset($this->nodeList[self::TYPE_REPEAT][$name]);
            }

            // Remove nodes marked hidden
            foreach ($this->getNodeList(self::TYPE_VAR) as $var => $nodes) {
                foreach ($nodes as $node) {
                    if (!$node instanceof DOMElement || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_DELETE)) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Remove choice node marked hidden
            foreach ($this->getNodeList(self::TYPE_CHOICE) as $choice => $nodes) {
                foreach ($nodes as $node) {
                    if (!$node instanceof DOMElement || !isset($node->parentNode) || !$node->parentNode) continue;
                    if ($node->hasAttribute(self::ATTR_DELETE)) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // Insert headers
            $headNode = $this->head;
            if ($headNode instanceof DOMElement) {
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
                    $n = $header['node'] ?? false;
                    if ($n instanceof DOMElement) {
                        $n->parentNode->insertBefore($node, $n);
                        $n->parentNode->insertBefore($nl, $n);
                    } else {
                        if (strtolower($header['elementName']) == 'meta' && $this->title) {
                            // insert meta tags above <title> tag where possible
                            // Note this may reverse the order, not sure that matters for meta tags tho
                            $headNode->insertBefore($node, $this->title);
                            $headNode->insertBefore($nl, $this->title);
                            $headNode->insertBefore($t, $this->title);
                        } else {
                            $headNode->append($node);
                            $headNode->append($t);
                            $headNode->append($nl);
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
     * Get the parsed state of the template.
     * Changes cannot be made to a parsed template.
     * `reset()` must be called to re-parse the template.
     */
    public function isParsed(): bool
    {
        return $this->parsed;
    }

    public function getDocument(bool $parse = true): ?DOMDocument
    {
        if ($parse && !$this->isParsed()) $this->parseDoc();
        return $this->document;
    }

    /**
     * Return the document as an HTML string
     */
    public function toString(bool $parse = true): string
    {
        $str = '';
        try {
            $doc = $this->getDocument($parse);
            $str = strval($doc->saveHTML($doc->documentElement));
            // Add html5 doctype
            if ($this->html5 && strtolower(substr($str, 0, 15)) != '<!doctype html>') {
                $str = "<!doctype html>\n" . $str;
            }
        } catch (\Exception $e) {
            error_log($e->__toString());
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



    // TODO: replace with adapters/Parsers
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
    public function getOriginalDocument(): DOMDocument
    {
        return $this->orgDocument;
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
    public function getTitleElement(): ?DOMElement
    {
        return $this->title;
    }

    /**
     * Return the head node if it exists.
     */
    public function getHeadElement(): ?DOMElement
    {
        return $this->head;
    }

    /**
     * Return the current list of header nodes
     * Holds arrays of header descriptions in the format of:
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
     * Return the root document node.
     * IE: DomDocument->documentElement
     */
    public function getRootElement(): DOMElement
    {
        return $this->document->documentElement;
    }

    /**
     * Gets the page title node text.
     */
    public function getTitleText(): string
    {
        return $this->title->nodeValue;
    }

    /**
     * Return the body node.
     */
    public function getBodyElement(): ?DOMElement
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
     * Show/Hide a choice or a var node
     */
    public function setVisible(string $choice, bool $b = true): Template
    {
        $nodes = $this->getNodeList(self::TYPE_CHOICE, $choice);
        foreach ($nodes as $node) {
            if ($b) {
                $node->removeAttribute(self::ATTR_DELETE);
            } else {
                $node->setAttribute(self::ATTR_DELETE, self::ATTR_DELETE);
            }
        }
        return $this;
    }

    /**
     * Return a form object from the document.
     * @deprecated move top a parser ??
     */
    public function getForm(string $id = ''): ?Form
    {
        if (!$this->isParsed() && isset($this->form[$id])) {
            return new Form($this->form[$id], $this->formElement[$id], $this);
        }
        return null;
    }


    /**
     * Get a repeating region from a document.
     */
    public function getRepeat(string $repeat): ?Repeat
    {
        $repeat = $this->getNode(self::TYPE_REPEAT, $repeat);
        if ($repeat instanceof Repeat) {
            return clone $repeat;
        }
        return null;
    }

    /**
     * Get a DOMElement from the document based on its unique
     * ID attributes should be unique for XHTML documents, multiple ids
     * are ignored, only the first id node found is returned.
     */
    public function getElementById(string $id): ?DOMElement
    {
        return $this->getNode(self::TYPE_ID, $id);
    }

    /**
     * An internal function for the template and Parsers to add nodes to the Template
     * Not for external Template use.
     * The node can really be anything, but the template uses it for `\DOMElement` and `Dom\Repeat` objects
     */
    public function addNode(string $type, string $name, mixed $node): self
    {
        if (!isset($this->nodeList[$type])) $this->nodeList[$type] = [];
        if (!isset($this->nodeList[$type][$name])) $this->nodeList[$type][$name] = [];
        $this->nodeList[$type][$name][] = $node;
        return $this;
    }

    /**
     * Return all available nodes for a node type and name from the nodeList array
     * Returns all nodes for the type if the name is null
     */
    public function getNodeList(string $type, ?string $name = null): array
    {
        if (is_null($name)) return $this->nodeList[$type] ?? [];
        if (empty($name) || !isset($this->nodeList[$type])) return [];
        return $this->nodeList[$type][$name] ?? [];
    }

    /**
     * Return the first node with type and name in the nodeList array
     */
    public function getNode(string $type, string $name): mixed
    {
        $list = $this->getNodeList($type, $name);
        return $list[0] ?? null;
    }

    /**
     * Returns true if the node type and name are set in the nodeList array
     */
    public function hasNode(string $type, string $name): bool
    {
        if (empty($name)) return false;
        $list = $this->getNodeList($type, $name);
        return count($list) > 0;
    }

    /**
     * Mark a node name/type group for deletion on the parsing of the template
     * returns the nodes marked for deletion
     */
    public function removeNode(string $type, string $name): array
    {
        $list = $this->getNodeList($type, $name);
        foreach ($list as $node) {
            $node->setAttribute(self::ATTR_DELETE, self::ATTR_DELETE);
        }
        return $list;
    }

    public function varExists(string $var): bool
    {
        return $this->hasNode(self::TYPE_VAR, $var);
    }

    public function choiceExists(string $choice): bool
    {
        return $this->hasNode(self::TYPE_CHOICE, $choice);
    }

    public function repeatExists(string $repeat): bool
    {
        return $this->hasNode(self::TYPE_REPEAT, $repeat);
    }

    public function idExists(string $id): bool
    {
        return $this->hasNode(self::TYPE_ID, $id);
    }

    // -------------- Document Modifier code ---------------

    public function addCss(string $var, array|string $class): Template
    {
        if (is_string($class)) {
            $class = trim($class);
            $list = explode(' ', $class);
        } else {
            $list = $class;
        }
        $list2 = explode(' ', $this->getAttr($var, 'class'));
        $list = array_merge($list2, $list);
        $list = array_unique($list);

        $classStr = trim(implode(' ', $list));
        if ($classStr) {
            $this->setAttr($var, 'class', $classStr);
        }
        return $this;
    }

    public function removeCss(string|DOMElement $var, string $class): Template
    {
        $str = $this->getAttr($var, 'class');
        $str = preg_replace('/(' . $class . ')\s?/', '', trim($str));
        $this->setAttr($var, 'class', $str);
        return $this;
    }

    public function setAttr(string $var, array|string $attr, null|string|int|float $value = null): Template
    {
        if ($this->isParsed()) return $this;
        if (!is_array($attr)) $attr = [$attr => (string)$value];
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
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

    public function getAttr(string $var, string $attr): string
    {
        if ($this->isParsed()) return '';
        $node = $this->getNode(self::TYPE_VAR, $var);
        if ($node instanceof DOMElement) {
            return $node->getAttribute($attr);
        }
        return '';
    }

    public function removeAttr(string $var, string $attr): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $n) {
            $n->removeAttribute($attr);
        }
        return $this;
    }

    /**
     * Sets the document title text if available.
     */
    public function setTitleText(string $value): Template
    {
        if (!$this->isParsed()) {
            if ($this->title == null) {
                error_log(__CLASS__.'::setTitleText() This document has no title node.');
                return $this;
            }
            $this->removeChildren($this->title);
            $this->title->nodeValue = htmlentities(html_entity_decode($value));
        }
        return $this;
    }

    /**
     * Append an element to the Head tag of the document.
     *
     * In the form of:
     *  <$elementName $attributes[$key]="$attributes[$key].$value">$value</$elementName>
     *
     * Things to note:
     *   - If the template has been parsed, the head element will not be added
     *   - Node is not created until parsing
     *   - If the template is parsed without a head tag, elements will be ignored
     *   - Header elements will iterate up the parent when using the insert/append/replace Template methods
     *   - Only unique headers are added, duplicate headers are ignored
     *
     * @param array<string, string> $attributes An associative array of (attr, value) pairs.
     * @param DOMElement|null $node (optional) If set,the tag will be appended after the supplied node
     */
    public function appendHeadElement(string $elementName, array $attributes, string $value = '', ?DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        $preKey = $elementName . $value;
        $ignore = ['content', 'type', self::ATTR_DATA_TRACE];
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
     * Use this to add meta-tags to the template head tag
     *
     * @param DOMElement|null $node (optional) If set,the tag will be appended after the supplied node
     * @see appendHeadElement()
     */
    public function appendMetaTag(string $name, string $content, ?DOMElement $node = null): Template
    {
        return $this->appendHeadElement('meta', array('name' => $name, 'content' => $content), '', $node);
    }

    /**
     * Append a CSS file to the template head element
     *
     * @param DOMElement|null $node (optional) If set,the tag will be appended after the supplied node
     * @see appendHeadElement()
     */
    public function appendCssUrl(string $styleUrl, array $attrs = [], ?DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        $attrs['rel'] = 'stylesheet';
        $attrs['href'] = $styleUrl;
        $trace = $this->getTracer();
        if ($trace) $attrs[self::ATTR_DATA_TRACE] =  $trace;
        $this->appendHeadElement('link', $attrs, '', $node);
        return $this;
    }

    /**
     * Append CSS to the template parentElement or document body if exists
     */
    public function appendCss(string $styles, array $attrs = []): Template
    {
        $styles = trim($styles);
        if (!$styles || $this->isParsed()) return $this;

        // append js to body tag
        $parentNode = $this->getBodyElement();
        if (is_null($parentNode)) {
            // append to parent template tag
            $parentNode = $this->document->documentElement;
        }
        if (is_null($parentNode)) {
            throw new Exception("cannot locate template parent to append CSS");
        }

        //$nl = $this->document->createTextNode("\n");
        $node = $this->document->createElement('style');
        $ct = $this->document->createCDATASection("\n" . $styles . "\n");
        $node->appendChild($ct);
        foreach ($attrs as $k => $v) {
            $node->setAttribute($k, $v);
        }
        $parentNode->appendChild($node);

        $trace = $this->getTracer();
        if ($trace) {
            $node->setAttribute(self::ATTR_DATA_TRACE, $trace);
        }
        return $this;
    }

    /**
     * Append CSS to the template head element
     *
     * @param DOMElement|null $node (optional) If set,the tag will be appended after the supplied node
     * @see appendHeadElement()
     */
    public function appendHeadCss(string $styles, array $attrs = [], ?DOMElement $node = null): Template
    {
        if (!trim($styles) || $this->isParsed()) return $this;
        $trace = $this->getTracer();
        if ($trace) $attrs[self::ATTR_DATA_TRACE] =  $trace;
        $this->appendHeadElement('style', $attrs, "\n" . $styles . "\n", $node);
        return $this;
    }

    /**
     * Append a JavaScript file to the template head element
     *
     * @param DOMElement|null $node (optional) If set,the tag will be appended after the supplied node
     * @see appendHeadElement()
     */
    public function appendJsUrl(string $urlString, array $attrs = [], ?DOMElement $node = null): Template
    {
        if ($this->isParsed()) return $this;
        if (!isset($attrs['type']) && !$this->isHtml5()) {
            $attrs['type'] = 'text/javascript';
        }
        if (!isset($attrs['src'])) {
            $attrs['src'] = $urlString;
        }

        $trace = $this->getTracer();
        if ($trace) $attrs[self::ATTR_DATA_TRACE] =  $trace;

        $this->appendHeadElement('script', $attrs, '', $node);
        return $this;
    }

    /**
     * Append JavaScript to the template parentElement or document body if exists
     */
    public function appendJs(string $js, array $attrs = []): Template
    {
        $js = trim($js);
        if (empty($js) || $this->isParsed()) return $this;

        $parentNode = $this->getBodyElement();
        if (is_null($parentNode)) {
            $parentNode = $this->document->documentElement;
        }
        if (is_null($parentNode)) {
            throw new Exception("cannot locate template parent to append JavaScript");
        }

        $node = $this->document->createElement('script');
        $ct = $this->document->createCDATASection("\n" . $js . "\n");
        $node->appendChild($ct);
        foreach ($attrs as $k => $v) {
            $node->setAttribute($k, $v);
        }
        $parentNode->appendChild($node);

        $trace = $this->getTracer();
        if ($trace) {
            $node->setAttribute(self::ATTR_DATA_TRACE, $trace);
        }
        return $this;
    }

    /**
     * Append JavaScript to the template head element
     *
     * @param DOMElement|null $node (optional) append the JS after the supplied node
     * @see appendHeadElement()
     */
    public function appendHeadJs(string $js, array $attrs = [], ?DOMElement $node = null): Template
    {
        if (empty(trim($js)) || $this->isParsed()) return $this;
        $trace = $this->getTracer();
        if ($trace) $attrs[self::ATTR_DATA_TRACE] =  $trace;
        $this->appendHeadElement('script', $attrs, $js, $node);
        return $this;
    }

    /**
     * return a string representing the calling code location for debugging
     */
    protected function getTracer(int $ignore = 2): string
    {
        $trace = debug_backtrace();
        if (self::$ENABLE_TRACER && !empty($trace[$ignore])) {
            return
                (!empty($trace[$ignore]['line']) ? '[' . $trace[$ignore]['line'] . '] ' : '') .
                (!empty($trace[$ignore]['class']) ? $trace[$ignore]['class'] . '::' : '') .
                (!empty($trace[$ignore]['function']) ? $trace[$ignore]['function'] . '()' : '');
        }
        return '';
    }

    /**
     * Append a template to the <body> tag, the supplied template
     * will be merged into other templates until a <body> tag
     * exists within the document
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
     * Merge the supplied body template with this document body template list.
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
     * Removes all child nodes from a var
     */
    public function empty(string $var): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            $this->removeChildren($node);
        }
        return $this;
    }

    /**
     * Get the text inside a var node.
     */
    public function getText(string $var): string
    {
        $node = $this->getNode(self::TYPE_VAR, $var);
        if ($node instanceof DOMElement) {
            return $node->textContent;
        }
        return '';
    }

    /**
     * Replace the text of a var element
     */
    public function setText(string $var, string $value): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
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
    public function appendText(string $var, string $value): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            $newNode = $this->document->createTextNode($value);
            $node->appendChild($newNode);
        }
        return $this;
    }

    /**
     * Prepend text to a var element
     */
    public function prependText(string $var, string $value): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            $newNode = $this->document->createTextNode((string)$value);
            $node->insertBefore($newNode, $node->firstChild);
        }
        return $this;
    }


    /**
     * Return the HTML including the node contents
     */
    public function getHtml(string $var): string
    {
        $html = '';
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        if (count($nodes)) {
            $doc = new DOMDocument();
            $doc->appendChild($doc->importNode($nodes[0], true));
            $html = trim(strval($doc->saveHTML()));
        }
        return $html;
    }

    /**
     * Insert HTML content into a var element removing any existing html content.
     */
    public function setHtml(string $var, string $html): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            try {
                $this->removeChildren($node);
                self::insertDomHtml($node, $html, $this->encoding);
            } catch (\Exception $e) {
                error_log($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML content into a var element
     */
    public function appendHtml(string $var, string $html): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            try {
                self::appendDomHtml($node, $html, $this->encoding);
            } catch (\Exception $e) {
                error_log($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Append HTML content into a var element
     */
    public function prependHtml(string $var, string $html): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $node) {
            try {
                self::prependDomHtml($node, $html);
            } catch (\Exception $e) {
                error_log($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Alias to Template::setHtml()
     * @deprecated use Template::setHtml()
     */
    public function insertHtml(string $var, string $html): Template
    {
        return $this->setHtml($var, $html);
    }

    /**
     * Replace a template var element with the supplied HTML
     *
     * @param bool $preserveAttrs Retain any attributes from the exiting template tag
     * @note If duplicate attributes exist, the inserted HTML gets precedence
     */
    public function replaceHtml(string $var, string $html, bool $preserveAttrs = true): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        $this->empty($var);
        foreach ($nodes as $i => $node) {
            try {
                $newNode = self::replaceDomHtml($node, $html, $this->encoding, $preserveAttrs);
                if ($newNode instanceof DOMElement) {
                    // TODO: Test this actually updates the class var array, may need to access it directly
                    $nodes[$i] = $newNode;
                }
            } catch (\Exception $e) {
                error_log($e->__toString());
            }
        }
        return $this;
    }

    /**
     * Insert HTML formatted text into a DOMNode.
     */
    public static function insertDomHtml(DOMNode $element, string $html, string $encoding = 'UTF-8'): ?DOMNode
    {
        if ($html == null) return null;

        $elementDoc = $element->ownerDocument;
        while ($element->hasChildNodes()) {
            $element->removeChild($element->childNodes->item(0));
        }
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
     * Append HTML text into a DOMNode.
     */
    public static function appendDomHtml(DOMNode $element, string $html, string $encoding = 'UTF-8'): ?DOMNode
    {
        if (!$html) return null;
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
     * Append HTML text into a DOMNode.
     */
    public static function prependDomHtml(DOMNode $element, string $html): ?DOMNode
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
     * Replace HTML on a DOMNode
     * This will replace the existing node not just its inner contents.
     *
     * @param bool $preserveAttrs Retain any attributes from the exiting template tag
     * @note If duplicate attributes exist, the inserted HTML gets precedence
     */
    public static function replaceDomHtml(DOMNode $element, string $html, string $encoding = 'UTF-8', bool $preserveAttrs = true): ?DOMNode
    {
        if (!$html) return null;

        if (str_starts_with($html, '<?xml')) {
            $html = substr($html, strpos($html, "\n", 5) + 1);
        }
        $elementDoc = $element->ownerDocument;

        $contentNode = self::makeContentNode($html);
        $contentNode = $contentNode->firstChild;
        $contentNode = $elementDoc->importNode($contentNode, true);
        if ($contentNode instanceof DOMElement && $element->hasAttributes() && $preserveAttrs) {
            foreach ($element->attributes as $attr) {
                $contentNode->setAttribute($attr->nodeName, $attr->nodeValue);
            }
        }
        $element->parentNode->replaceChild($contentNode, $element);
        return $contentNode;
    }

    /**
     * Insert a DOMDocument into a var element
     * The var tag will not be replaced only its contents
     */
    public function insertDocHtml(string $var, DOMDocument $doc): Template
    {
        if ($this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
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
     * Append DOMDocument content to var node
     */
    public function appendDocHtml(string $var, DOMDocument $doc): Template
    {
        if (!$doc->documentElement || $this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $el) {
            $node = $this->document->importNode($doc->documentElement, true);
            $el->appendChild($node);
        }
        return $this;
    }

    /**
     * Prepend DOMDocument content to var node
     */
    public function prependDocHtml(string $var, DOMDocument $doc): Template
    {
        if (!$doc->documentElement || $this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
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
     * Replace DOMDocument content with var node
     *
     * @param bool $preserveAttrs Retain any attributes from the exiting template tag
     * @note If duplicate attributes exist, the inserted HTML gets precedence
     */
    public function replaceDocHtml(string $var, DOMDocument $doc, bool $preserveAttrs = true): Template
    {
        if (!$doc->documentElement || $this->isParsed()) return $this;
        $nodes = $this->getNodeList(self::TYPE_VAR, $var);
        foreach ($nodes as $i => $node) {
            $newNode = $this->document->importNode($doc->documentElement, true);
            if ($newNode instanceof DOMElement) {
                if ($node->hasAttributes() && $preserveAttrs) {
                    foreach ($node->attributes as $attr) {
                        if ($newNode->hasAttribute($attr->nodeName)) continue;
                        $newNode->setAttribute($attr->nodeName, $attr->nodeValue);
                    }
                }
                $node->parentNode->replaceChild($newNode, $node);
                // TODO: Test this actually updates the class var array, may need to access it directly
                //$this->var[$var][$i] = $newNode;
                $nodes[$i] = $newNode;

            }
        }
        return $this;
    }

    /**
     * Parse and Insert a template into a var element
     * The var tag will not be replaced only its contents
     *
     * Any headers added with appendHeadElement() will be copied to this template.
     */
    public function insertTemplate(string $var, Template $template): Template
    {
        if ($this->isParsed()) return $this;
        $this->mergeTemplate($template);
        return $this->insertDocHtml($var, $template->getDocument());
    }

    /**
     * Append a template to a var element, it will parse the template before appending it
     *
     * Any headers added with appendHeadElement() will be copied to this template.
     */
    public function appendTemplate(string $var, Template $template): Template
    {
        if ($this->isParsed()) return $this;
        $this->mergeTemplate($template);
        return $this->appendDocHtml($var, $template->getDocument());
    }

    /**
     * Prepend a template to a var element, it will parse the template before appending it
     *
     * Any headers added with appendHeadElement() will be copied to this template.
     */
    public function prependTemplate(string $var, Template $template): Template
    {
        if ($this->isParsed()) return $this;
        $this->mergeTemplate($template);
        return $this->prependDocHtml($var, $template->getDocument());
    }

    /**
     * Replace a var node with the supplied Template
     * Any headers added with appendHeadElement() will be copied to this template.
     *
     * @param bool $preserveAttrs Retain any attributes from the exiting template tag
     * @note If duplicate attributes exist, the inserted HTML gets precedence
     */
    public function replaceTemplate(string|DOMElement $var, Template $template, bool $preserveAttrs = true): Template
    {
        if ($this->isParsed()) return $this;
        $this->mergeTemplate($template);
        return $this->replaceDocHtml($var, $template->getDocument(), $preserveAttrs);
    }


    /**
     * Prepare XML/HTML markup string ready for insertion into a node.
     * Some methods require that there be a start and end tag before a node can be inserted.
     */
    protected static function makeContentNode(string $markup, string $encoding = 'UTF-8'): DOMNode
    {
        $id = '_c_o_n__';
        $html = sprintf('<?xml encoding="'.$encoding.'"?><div id="%s">%s</div>', $id, $markup);
        $doc = new DOMDocument();
        $doc->substituteEntities = false;
        libxml_use_internal_errors(true);

        $ok = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$ok) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $str .= "\n\n" . $markup . "\n";

            throw new Exception('Error Parsing DOM Template', 0, null, $str);
        }
        return $doc->getElementById($id);
    }

    /**
     * Removes all children from a node.
     * To be used internally
     */
    protected function removeChildren(DOMNode $node): Template
    {
        while ($node->hasChildNodes()) {
            $node->removeChild($node->childNodes->item(0));
        }
        return $this;
    }

    /**
     * Get a parser instance for this template
     */
    public function getParser(string $class): ?ParserInterface
    {
        return $this->parsers[$class] ?? null;
    }

    public static function addTemplateParser(string $parserClass): void
    {
        if (!class_exists($parserClass)) {
            throw new Exception('Parser class does not exist: ' . $parserClass);
        }
        self::$TEMPLATE_PARSERS[$parserClass] = $parserClass;
    }

    public static function getTemplateParsers(): array
    {
        return self::$TEMPLATE_PARSERS;
    }

    public static function resetTemplateParsers(): void
    {
        self::$TEMPLATE_PARSERS = [];
    }
}