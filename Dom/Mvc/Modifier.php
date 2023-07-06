<?php
namespace Dom\Mvc;

use Dom\Mvc\Modifier\FilterInterface;
use DOMComment;

/**
 * This class is designed to take a DOMDocument, traverse it and pass each Node to
 * the filters attached.
 *
 * The main aim of this object is to make final pass alterations to a dom document before rendering.
 * This ensures that we only traverse the DOM tree once on the final render stage.
 *
 * The modifier traverses each node and on each DOMElement node it will run all the filters
 * in the $filters array on that node before continuing on to the next one.
 *
 * NOTE: It is important to note that you do not use the DOM functions to remove a node
 *       in your filters. Use $filter->getDomModifier()->removeNode($node);
 *       Then the modifier will remove all nodes in the trash on cleanup.
 *
 * Example:<br/>
 * <code>
 *      $dm = new \Tk\Dom\Modifier\Modifier();
 *      $dm->add(new \Tk\Dom\Modifier\Filter\Path($apUrl, $templateUrl));
 *      $dm->execute($template->getDocument());
 * </code>
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Modifier
{
    /**
     * @var array|FilterInterface[]
     */
    protected array $filters = [];

    /**
     * @var array|\DOMNode[]
     */
    protected array $nodeTrash = [];

    protected ?\DOMElement $head = null;

    protected ?\DOMElement $body = null;

    protected bool $inHead = false;

    protected bool $inBody = false;


    /**
     * add a Dome modifier filter object to the queue
     */
    public function addFilter($name, FilterInterface $mod): FilterInterface
    {
        $mod->setDomModifier($this);
        $this->filters[$name] = $mod;
        return $mod;
    }

    public function getFilter($name): ?FilterInterface
    {
        return $this->filters[$name] ?? null;
    }

    public function getHead(): ?\DOMElement
    {
        return $this->head;
    }

    public function inHead(): bool
    {
        return $this->inHead;
    }

    public function getBody(): ?\DOMElement
    {
        return $this->body;
    }

    public function inBody(): bool
    {
        return $this->inBody;
    }

    /**
     * Use this method to delete nodes,
     * They will be added to a queue for removal after traversal of template
     * If you used the DOM remove a node while traversing
     * the DOM tree traversing will get screwed up and work unpredictably.
     */
    public function removeNode(\DOMNode $node): Modifier
    {
        $this->nodeTrash[] = $node;
        return $this;
    }

    /**
     * Call this method to start traversing a document
     */
    public function execute(\DOMDocument $doc): \DOMDocument
    {
        $doc->normalizeDocument();
        foreach ($this->filters as $mod) {
            $mod->init($doc);
        }
        $this->traverse($doc->documentElement);
        foreach ($this->filters as $mod) {
            $mod->postTraverse($doc);
        }

        // Clear trash
        foreach ($this->nodeTrash as $node) {
            $node->parentNode?->removeChild($node);
        }
        gc_collect_cycles();

        return $doc;
    }

    /**
     * Traverse a document converting element attributes.
     *
     * @param \DOMNode $node
     */
    private function traverse(\DOMNode $node): void
    {
        if ($node->nodeType == \XML_ELEMENT_NODE) {
            /** @var $node \DOMElement */
            if ($node->nodeName == 'head') {
                $this->head = $node;
                $this->inHead = true;
            }
            if ($node->nodeName == 'body') {
                $this->body = $node;
                $this->inBody = true;
            }
            foreach ($this->filters as $mod) {
                if (!$mod->isEnabled()) continue;
                $mod->executeNode($node);
            }
        }
        if ($node->nodeType == \XML_COMMENT_NODE) {
            /** @var $node DOMComment */
            foreach ($this->filters as $mod) {
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
                $this->inHead = false;
            }
            if ($node->nodeName == 'body') {
                $this->inBody = false;
            }
        }

    }

}