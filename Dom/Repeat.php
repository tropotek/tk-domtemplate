<?php
namespace Dom;

/**
 * A repeat region is a sub template of a parent templates nodes.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @link http://www.domtemplate.com/
 * @license Copyright 2007
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
     * @return \DOMElement|\DOMNode
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
     * @return \DOMElement|\DOMNode
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
