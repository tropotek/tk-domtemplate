<?php
namespace Dom;

/**
 * A repeat region is a sub template of its parent template.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Repeat extends Template
{

    protected string       $repeatName = '';
    protected ?\DOMElement $repeatNode = null;
    protected ?Template    $parent     = null;


    public function __construct(\DOMElement $node, Template $parent)
    {
        $this->repeatNode = $node;
        $this->repeatName = $node->getAttribute('repeat');
        $this->parent = $parent;

        $repeatDoc = new \DOMDocument();
        $tplNode = $repeatDoc->importNode($node, true);
        if ($tplNode instanceof \DOMElement) {
            $tplNode->removeAttribute('repeat');
            $var = $tplNode->getAttribute(self::$ATTR_VAR);
            $tplNode->setAttribute(self::$ATTR_VAR, $var . ' ' . $this->repeatName);
        }
        $repeatDoc->appendChild($tplNode);

        parent::__construct($repeatDoc, $parent->getEncoding());
    }

    /**
     * Append a repeating region to the document.
     * Repeating regions are appended to the supplied var.
     * If the var is null or '' then the repeating region is appended
     * to the original location in the parent template.
     */
    public function appendRepeat(string $var = '', ?Template $destRepeat = null): \DOMNode|false
    {
        if ($this->getParent()->isParsed()) return false;

        $this->getParent()->headers = array_merge($this->getParent()->getHeaderList(), $this->getHeaderList());
        $this->getParent()->bodyTemplates = array_merge($this->getParent()->getBodyTemplateList(), $this->getBodyTemplateList());

        $appendNode = $this->repeatNode;
        if ($var) {
            $appendNode = $this->getParent()->getNode(self::TYPE_VAR, $var);
            if ($destRepeat && $destRepeat->getNode(self::TYPE_VAR, $var)) {
                $appendNode = $destRepeat->getNode(self::TYPE_VAR, $var);
            }
        }

        $insertNode = $appendNode->ownerDocument->importNode($this->getDocument()->documentElement, true);
        if (!$var && $appendNode->parentNode) {
            $appendNode->parentNode->insertBefore($insertNode, $appendNode);
        } else {
            $appendNode->appendChild($insertNode);
        }

        return $insertNode;
    }

    /**
     * Prepend a repeating region to the document.
     * Repeating regions are prepended to the supplied var.
     * If the var is null or '' then the repeating region is prepended
     * to is original location in the parent template.
     */
    public function prependRepeat(string $var = '', ?Template $destRepeat = null): \DOMNode|false
    {
        if ($this->getParent()->isParsed()) return false;

        $this->getParent()->headers = array_merge($this->getParent()->getHeaderList(), $this->getHeaderList());
        $this->getParent()->bodyTemplates = array_merge($this->getParent()->getBodyTemplateList(), $this->getBodyTemplateList());
        $appendNode = $this->repeatNode;
        if ($var) {
            $appendNode = $this->getParent()->getNode(self::TYPE_VAR, $var);
            if ($destRepeat && $destRepeat->getNode(self::TYPE_VAR, $var)) {
                $appendNode = $destRepeat->getNode(self::TYPE_VAR, $var);
            }
        }

        $insertNode = $appendNode->ownerDocument->importNode($this->getDocument()->documentElement, true);

        if ($appendNode->firstChild) {
            $appendNode->insertBefore($insertNode, $appendNode->firstChild);
        } else {
            $appendNode->appendChild($insertNode);
        }

        return $insertNode;
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
    public function getParent(): ?Template
    {
        return $this->parent;
    }

}
