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
     * Initialize any custom modifier properties.
     * Called before the DOM tree is traversed.
     */
    abstract function init(\DOMDocument $doc): void;

    /**
     * Called when traversing each DOMElement node in the DOM tree.
     */
    abstract function executeNode(\DOMElement $node): void;

    /**
     * Called when traversing each DOMComment node in the DOM tree.
     */
    public function executeComment(\DOMComment $node): void { }

    /**
     * Final call after the DOM tree is traversed.
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