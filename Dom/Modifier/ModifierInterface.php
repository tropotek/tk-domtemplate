<?php
namespace Dom\Modifier;

use Dom\Modifier;

/**
 * The interface for all Modifier objects
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class ModifierInterface
{

    protected ?Modifier $domModifier = null;

    protected bool $enabled = true;

    /**
     * pre init the modifier
     */
    abstract function init(\DOMDocument $doc): void;

    /**
     * The code to perform any modification to the node goes here.
     */
    abstract function executeNode(\DOMElement $node): void;

    /**
     * Execute code on the current Comment Node
     */
    public function executeComment(\DOMComment $node): void { }

    /**
     * called once after the DOM tree is traversed
     */
    public function postTraverse(\DOMDocument $doc): void { }


    public function setDomModifier(Modifier $dm): ModifierInterface
    {
        $this->domModifier = $dm;
        return $this;
    }

    public function getDomModifier(): ?Modifier
    {
        return $this->domModifier;
    }

    public function setEnable(bool $b): ModifierInterface
    {
        $this->enabled = $b;
        return $this;
    }

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