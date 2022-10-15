<?php
namespace Dom\Mvc\Modifier;

/**
 * Append all scripts to the bottom of the body tag.
 * This is a current technique employed by designers
 * for mobile devices to load faster.
 *
 */
class JsLast extends FilterInterface
{
    /**
     * Used to set an order priority to a node
     * if < 0 the node is placed higher in the tree 
     * if > 0 the node is placed lower in the tree
     */
    public static string $ATTR_PRIORITY = 'data-jsl-priority';

    /**
     * Used to ensure the node is not moved/sorted.
     */
    public static string $ATTR_STATIC = 'data-jsl-static';
    
    /**
     * Flag to ensure the filter is run once only
     */
    private bool $notRun = true;

    private array $head = [];

    private array $body = [];


    /**
     * pre init the front controller
     */
    public function init(\DOMDocument $doc) { }

    /**
     * Call this method to travers a document
     */
    public function executeNode(\DOMElement $node)
    {
        if ($node->nodeName == 'script' && !$node->hasAttribute(self::$ATTR_STATIC)) {
            if ($this->domModifier->inHead()) {
                $this->head[] = $node;
            } else {
                $this->body[] = $node;
            }
        }
    }

    /**
     * called after DOM tree is traversed
     */
    public function postTraverse(\DOMDocument $doc)
    {
        if ($this->domModifier->getBody() && $this->notRun) {
            $nodeList = array_merge($this->body, $this->head);
            $this->notRun = false;
            // Sort the script nodes in order of the priority attribute if it exists
            $this->usort($nodeList, function ($a, $b) {
                $aPri = 0;
                if ($a->hasAttribute(self::$ATTR_PRIORITY)) {
                    $aPri = (int)$a->getAttribute(self::$ATTR_PRIORITY);
                }
                $bPri = 0;
                if ($b->hasAttribute(self::$ATTR_PRIORITY)) {
                    $bPri = (int)$b->getAttribute(self::$ATTR_PRIORITY);
                }
                if ($aPri == $bPri) {
                    return 0;
                }
                return ($aPri < $bPri) ? -1 : 1;
            });
            
            foreach ($nodeList as $child) {
                $newNode = $child->cloneNode(true);
                $this->domModifier->removeNode($child);
                
                $nl = $newNode->ownerDocument->createTextNode("\n");
                $this->domModifier->getBody()->appendChild($nl);
                $this->domModifier->getBody()->appendChild($newNode);
            }
        }

    }

    /**
     * This is a stable sort the php sort does not 
     * keep the original order when items are not to be sorted.
     * 
     * @param array $array
     * @param callable $value_compare_func
     * @see https://github.com/vanderlee/PHP-stable-sort-functions/blob/master/classes/StableSort.php
     */
    private function usort(array &$array, callable $value_compare_func): void
    {
        $index = 0;
        foreach ($array as &$item) {
            $item = array($index++, $item);
        }
        usort($array, function($a, $b) use($value_compare_func) {
            $result = call_user_func($value_compare_func, $a[1], $b[1]);
            return $result == 0 ? $a[0] - $b[0] : $result;
        });
        foreach ($array as &$item) {
            $item = $item[1];
        }
    }

}
