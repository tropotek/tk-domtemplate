<?php
namespace Dom\Loader\Adapter;

use \Dom\Template;
use \Dom\Loader;

/**
 * Default adapter for the loader object.
 *
 * This should be run last after all other adapters have been tried
 *
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
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
     */
    public function load($xhtml, $class)
    {
        try {
            return Template::load($xhtml);
        } catch (\Exception $e) { error_log($e->getMessage()); }
    }

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @param $class
     * @return Template
     */
    public function loadFile($path, $class)
    {
        try {
            return Template::loadFile($path);
        } catch (\Exception $e) { error_log($e->getMessage()); }
    }

}