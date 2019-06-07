<?php
namespace Dom;

/**
 * A repeat region is a sub template of a parent templates nodes.
 *
 * @author Michael Mifsud
 * @author Darryl Ross
 * @see http://www.domtemplate.com/
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
        $node->removeAttribute('repeat');

        $repeatDoc = new \DOMDocument();
        $tplNode = $repeatDoc->importNode($node, true);
        $repeatDoc->appendChild($tplNode);

        parent::__construct($repeatDoc, $parent->getEncoding());
    }


    public function __clone()
    {
        parent::__clone();
        // TODO: To implement this we need a function like addVar that checks if a node
        //       already exists in the list under a different name, or we check when we append
        //       nodes to a var and only add it once pre not even if it is under a different var name?????
        //       All this may slow it down to much.
        //$this->var[$this->repeatName][] = $this->document->documentElement;
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
        // $this->mergeTemplate($template);
        $this->repeatParent->setHeaderList(array_merge($this->repeatParent->getHeaderList(), $this->getHeaderList()));
        $this->repeatParent->setBodyTemplateList(array_merge($this->repeatParent->getBodyTemplateList(), $this->getBodyTemplateList()));

        $appendNode = $this->repeatNode;
        if ($var) {
            if ($this->repeatParent) {
                $appendNode = $this->repeatParent->getVar($var);
            }
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
                $appendNode = $this->repeatParent->getVar($var);
            }
            if ($destRepeat && $destRepeat->getVar($var)) {
                $appendNode = $destRepeat->getVar($var);
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
