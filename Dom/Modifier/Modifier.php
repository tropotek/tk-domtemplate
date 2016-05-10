<?php
namespace Dom\Modifier;

/**
 * This class is designed to take a \DOMDocument, traverse it and pass each Type to
 * the children filters.
 *
 * The main aim of this object is to make final last minute alterations to the dom template.
 *
 * This ensures that we only traverse the DOM tree once on the final render stage.
 *
 * New filters can be created by extending the \Tk\Dom\Modifier\Filter\Iface object.
 *
 * See the Tk\Config::getDomModifier() to get a basic implementation of the DomModifier.
 *
 * Example:<br/>
 * <code>
 * $dm = new \Tk\Dom\Modifier\Modifier();
 * $dm->add(new \Tk\Dom\Modifier\Filter\Path($apUrl, $templateUrl));
 * $dm->execute($template->getDocument());
 * </code>
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class Modifier
{
    /**
     * A list of filter objects
     * @var array
     */
    protected $list = array();

    /**
     * @var array
     */
    protected $nodeTrash = array();

    /**
     * @var \DOMElement
     */
    protected $head = null;

    /**
     * @var \DOMElement
     */
    protected $body = null;

    /**
     * @var bool
     */
    protected $inHead = false;

    /**
     * @var bool
     */
    protected $inBody = false;




    /**
     * add
     *
     * @param Filter\Iface $mod
     * @return Filter\Iface
     */
    public function add(Filter\Iface $mod)
    {
        $mod->setDomModifier($this);
        $this->list[] = $mod;
        return $mod;
    }

    /**
     * Set the mod list
     *
     * @param array $list
     */
    public function setList($list)
    {
        $this->list = $list;
    }

    /**
     * Get mod list
     *
     * @return array
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * Check to see if we are traversing inside the head node of the document
     *
     * @return \DOMNode
     */
    public function getHead()
    {
        return $this->head;
    }

    public function inHead()
    {
        return $this->inHead;
    }


    /**
     * Check to see if we are traversing inside the head node of the document
     *
     * @return \DOMNode
     */
    public function getBody()
    {
        return $this->body;
    }

    public function inBody()
    {
        return $this->inBody;
    }


    /**
     * Use this method to delete nodes,
     * They will be added to a queue for removal
     * If you used the DOM remove the DOM tree traversing will get
     * screwed up.
     *
     *
     * @param \DOMNode $node
     */
    public function removeNode($node)
    {
        $this->nodeTrash[] = $node;
    }

    /**
     * Call this method to traverse a document
     *
     * @param \DOMDocument $doc
     * @return \DOMDocument
     */
    public function execute(\DOMDocument $doc)
    {
        $doc->normalizeDocument();
        /** @var Filter\Iface $mod */
        foreach ($this->list as $mod) {
            $mod->init($doc);
        }
        $this->traverse($doc->documentElement);
        /** @var Filter\Iface $mod */
        foreach ($this->list as $mod) {
            $mod->postTraverse($doc);
        }
        // Clear trash
        if (count($this->nodeTrash)) {
            foreach ($this->nodeTrash as $node) {
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
            gc_collect_cycles();
        }
        return $doc;
    }

    /**
     * Traverse a document converting element attributes.
     *
     * @param \DOMNode $node
     */
    private function traverse(\DOMNode $node)
    {
        if (!$node) return;
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'head') {
                $this->head = $node;
                $this->inHead = true;
            }
            if ($node->nodeName == 'body') {
                $this->body = $node;
                $this->inBody = true;
            }
            /* @var $iterator Filter\Iface */
            foreach ($this->list as $mod) {
                if (!$mod->isEnabled()) continue;
                $mod->executeNode($node);
            }
        }
        if ($node->nodeType == \XML_COMMENT_NODE) {
            /* @var $iterator Filter\Iface */
            foreach ($this->list as $mod) {
                if (method_exists($mod, 'executeComment')) {
                    if (!$mod->isEnabled()) continue;
                    $mod->executeComment($node);
                }
            }
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->traverse($child);
            }
        }
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            if ($node->nodeName == 'head') {
                //$this->head = null;
                $this->inHead = false;
            }
            if ($node->nodeName == 'body') {
                //$this->body = null;
                $this->inBody = false;
            }
        }

    }




}