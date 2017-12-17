<?php
namespace Dom\Loader\Adapter;

use \Dom\Template;

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
     * @param string $path   The path to the template folder
     * @param string $ext   The template file extension, default 'xml'
     */
    public function __construct($path, $ext = 'xml')
    {
        $this->path = $path;
        $this->ext = trim($ext, '.');
    }

    /**
     * Load an xml/xhtml strings
     *
     * @param string $xhtml
     * @param string $class
     * @return Template
     * @throws \Dom\Exception
     */
    public function load($xhtml, $class)
    {
        return $this->loadFile('', $class);
    }

    /**
     * Load an xml/xhtml file
     *
     * @param string $path
     * @param string $class
     * @return Template|null
     * @throws \Dom\Exception
     */
    public function loadFile($path, $class)
    {
        $class = trim(str_replace('\\', '_', $class), '_');
        $tplPath = $this->path . '/' . $class . '.' . $this->ext;
        if (is_file($tplPath)) {
            return Template::loadFile($tplPath);
        }
    }

}