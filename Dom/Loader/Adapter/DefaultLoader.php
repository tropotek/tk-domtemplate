<?php
namespace Dom\Loader\Adapter;

use Dom\Exception;
use \Dom\Template;
use \Dom\Loader;

/**
 * Default adapter for the loader object.
 *
 * This should be run last after all other adapters have been tried
 *
 *
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class DefaultLoader extends Iface
{

    /**
     * Load an xml/xhtml strings
     *
     * @param $xhtml
     * @param $class
     * @return Template
     * @throws Exception
     */
    public function load($xhtml, $class)
    {
        return Template::load($xhtml);
    }

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @param $class
     * @return Template
     * @throws Exception
     */
    public function loadFile($path, $class)
    {
       return Template::loadFile($path);
    }

}