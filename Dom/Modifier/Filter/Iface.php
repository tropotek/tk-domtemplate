<?php
namespace Dom\Modifier\Filter;

use Dom\Modifier\Modifier;


/**
 * The interface for all DomModifier filter objects
 *
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
abstract class Iface
{

    /**
     * @var Modifier
     */
    protected $domModifier = null;

    /**
     * @var bool
     */
    protected $enabled = true;



    /**
     * Set Dom Modifier
     *
     * @param Modifier $dm
     */
    public function setDomModifier(Modifier $dm)
    {
        $this->domModifier = $dm;
    }

    /**
     * Set the enabled state of the object
     *
     * @param bool $b
     */
    public function setEnable($b)
    {
        $this->enabled = $b;
    }

    /**
     * Get the enabled status.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }


    /**
     * pre init the front controller
     *
     * @param \DOMDocument $doc
     */
    abstract function init($doc);


    /**
     * called after DOM tree is traversed
     *
     * @param \DOMDocument $doc
     */
    public function postTraverse($doc) { }


    /**
     * The code to perform any modification to the node goes here.
     *
     * @param \DOMElement $node
     */
    abstract function executeNode(\DOMElement $node);

}