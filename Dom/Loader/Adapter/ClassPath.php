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
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class ClassPath extends Iface
{
    /**
     * @var string
     */
    protected $ext = 'xtpl';

    /**
     * @var array
     */
    protected $path = '';

    /**
     * If set to true then the search path will be a single filename
     * EG:
     *    \App\Controller\Index   =>  {path}/App_Controller_index.xtpl
     *
     * Normal path would be
     *    \App\Controller\Index   =>  {path}/App/Controller/index.xtpl
     *
     * @var string
     */
    protected $useUnderscores = 'true';

    /**
     *
     * @param string $path The path to the template folder
     * @param string $ext The template file extension, default 'xtpl'
     * @param bools $useUnderscores
     */
    public function __construct($path, $ext = 'xtpl', $useUnderscores = true)
    {
        $this->path = $path;
        $this->ext = trim($ext, '.');
        $this->useUnderscores = $useUnderscores;
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
        $class = trim($class, '//');
        $classPath = str_replace('\\', '/', $class);
        if ($this->useUnderscores)
            $classPath = str_replace('\\', '_', $class);

        $tplPath = $this->path . '/' . trim($classPath, '/') . '.' . $this->ext;
        if (is_file($tplPath)) {
            return Template::loadFile($tplPath);
        }
    }

}