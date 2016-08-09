<?php
namespace Dom\Loader\Adapter;

use \Dom\Template;
use \Dom\Loader;

/**
 * This adapter will look for template files in the supplied
 * paths using the class name with underscores
 *
 * For example:
 *      if the supplied class is \App\Module\Index
 *      The class is converted to App_Module_Index
 *      The adapter will look for a template in the supplied path for example:
 *          /supplied/template/path/App_Module_Index.xml
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class ClassPath extends Iface
{
    /**
     * @var string
     */
    protected $ext = '';

    /**
     * @var array
     */
    protected $path = '';

    /**
     *
     * @param array $path   The path to the template folder
     * @param string $ext   The template file extension, default 'xml'
     */
    public function __construct($path, $ext = 'xml')
    {
        $this->path = $path;
        $this->ext = $ext;
    }

    /**
     * Load an xml/xhtml strings
     *
     * @param $xhtml
     * @param $class
     * @return Template
     */
    public function load($xhtml, $class)
    {
        return $this->loadFile('', $class);
    }

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @param $class
     * @return Template|null
     */
    public function loadFile($path, $class)
    {
        $class = trim(str_replace('\\', '_', $class), '_');
        $tplpath = $this->path . '/' . $class . '.' . $this->ext;
        if (is_file($tplpath)) {
            return Template::loadFile($tplpath);
        }
    }

}