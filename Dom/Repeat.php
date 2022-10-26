<?php
namespace Dom;

/**
 * A repeat region is a sub template of a parent templates nodes.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
 * @see http://www.tropotek.com/
 * @license Copyright 2007
 */
class Repeat extends Template
{
    protected ?\DOMElement $repeatNode = null;

    protected string $repeatName = '';

    protected ?Template $parent = null;


    public function __construct(\DOMElement $node, Template $parent)
    {
        $this->repeatNode = $node;
        $this->repeatName = $node->getAttribute('repeat');
        $this->parent = $parent;
        $node->removeAttribute('repeat');
        $node->setAttribute('var', $this->repeatName);

        $repeatDoc = new \DOMDocument();
        $tplNode = $repeatDoc->importNode($node, true);
        $repeatDoc->appendChild($tplNode);

        parent::__construct($repeatDoc, $parent->getEncoding());
    }

    /**
     * Append a repeating region to the document.
     * Repeating regions are appended to the supplied var.
     * If the var is null or '' then the repeating region is appended
     * to is original location in the parent template.
     * @throws \DOMException
     */
    public function appendRepeat(string $var = '', Template $destRepeat = null): ?\DOMElement
    {
        if ($this->getParent()->isParsed()) return null;

        $this->parent->headers = array_merge($this->parent->getHeaderList(), $this->getHeaderList());
        $this->parent->bodyTemplates = array_merge($this->parent->getBodyTemplateList(), $this->getBodyTemplateList());

        $appendNode = $this->repeatNode;
        if ($var) {
            $appendNode = $this->parent->getVar($var);
            if ($destRepeat && $destRepeat->getVar($var)) {
                $appendNode = $destRepeat->getVar($var);
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
     */
    public function prependRepeat(string $var = '', Template $destRepeat = null): ?\DOMElement
    {
        if ($this->getParent()->isParsed()) return null;

        $this->parent->headers = array_merge($this->parent->getHeaderList(), $this->getHeaderList());
        $this->parent->bodyTemplates = array_merge($this->parent->getBodyTemplateList(), $this->getBodyTemplateList());
        $appendNode = $this->repeatNode;
        if ($var) {
            $appendNode = $this->parent->getVar($var);
            if ($destRepeat && $destRepeat->getVar($var)) {
                $appendNode = $destRepeat->getVar($var);
            }
        }
        return $appendNode->ownerDocument->importNode($this->getDocument()->documentElement, true);
    }

    /**
     * Return the repeat node...
     */
    public function getRepeatNode(): ?\DOMElement
    {
        return $this->repeatNode;
    }

    /**
     * get the parent template this repeat belongs to.
     */
    public function getParent()
    {
        return $this->parent;
    }

}
