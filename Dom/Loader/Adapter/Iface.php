<?php
namespace Dom\Loader\Adapter;

use \Dom\Loader;

/**
 * Class Iface
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
abstract class Iface
{

    /**
     * @var Loader
     */
    protected $loader = null;


    /**
     * Load an xml/xhtml strings
     *
     * @param $xhtml
     * @param $class
     * @return \Dom\Template
     */
    abstract public function load($xhtml, $class);

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @param $class
     * @return \Dom\Template
     */
    abstract public function loadFile($path, $class);


    /**
     * @return Loader
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * @param Loader $loader
     */
    public function setLoader($loader)
    {
        $this->loader = $loader;
    }
}