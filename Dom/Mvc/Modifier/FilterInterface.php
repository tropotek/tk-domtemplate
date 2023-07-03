<?php
namespace Dom\Mvc\Modifier;

use Dom\Mvc\Modifier;

/**
 * The interface for all Modifier filter objects
 *
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class FilterInterface
{

    protected ?Modifier $domModifier = null;

    protected bool $enabled = true;


    /**
     * pre init the front controller
     */
    abstract function init(\DOMDocument $doc);


    /**
     * The code to perform any modification to the node goes here.
     */
    abstract function executeNode(\DOMElement $node);

    /**
     * Execute code on the current Comment Node
     */
    public function executeComment(\DOMComment $node) { }

    /**
     * called after DOM tree is traversed
     */
    public function postTraverse(\DOMDocument $doc) { }


    /**
     * Set Dom Modifier
     */
    public function setDomModifier(Modifier $dm): FilterInterface
    {
        $this->domModifier = $dm;
        return $this;
    }

    public function getDomModifier(): ?Modifier
    {
        return $this->domModifier;
    }

    /**
     * Set the enabled state of the object
     */
    public function setEnable(bool $b): FilterInterface
    {
        $this->enabled = $b;
        return $this;
    }

    /**
     * Get the enabled status.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    protected function addClass(string $classString, string $class): string
    {
        $arr = explode(' ', trim($classString));
        $arr = array_flip($arr);
        $arr[$class] = $class;
        $arr = array_flip($arr);
        return trim(implode(' ', $arr));
    }

    protected function removeClass(string $classString, string $class): string
    {
        $arr = explode(' ', trim($classString));
        $arr = array_flip($arr);
        unset($arr[$class]);
        $arr = array_flip($arr);
        return trim(implode(' ', $arr));
    }

}