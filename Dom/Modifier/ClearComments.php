<?php
namespace Dom\Modifier;


/**
 * A simple Dom Modifier to remove HTML comments from the DOM tree.
 *
 * @author Tropotek <http://www.tropotek.com/>
 * @requires https://github.com/tropotek/tk-framework (v8.0+)
 */
class ClearComments extends ModifierInterface
{

    /**
     * Initialize any custom modifier properties.
     * Called before the DOM tree is traversed.
     */
    public function init(\DOMDocument $doc): void { }

    /**
     * Called when traversing each DOMElement node in the DOM tree.
     */
    public function executeNode(\DOMElement $node): void { }

    /**
     * Called when traversing each DOMComment node in the DOM tree.
     */
    public function executeComment(\DOMComment $node): void
    {
        $this->getDomModifier()->removeNode($node);
    }

    /**
     * Final call after the DOM tree is traversed.
     */
    public function postTraverse(\DOMDocument $doc): void { }

}
