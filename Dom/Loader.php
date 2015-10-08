<?php
namespace Dom;

/**
 * Class Loader
 * Use this class to facilitate with the loading of template files
 *
 * You can add loader adapters to find templates in a cascading array
 *
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Loader
{


    /**
     * @var Loader
     */
    static $instance = null;

    /**
     * The class that called the loader getInstance() method
     * @var string
     */
    protected $calledClass = '';

    /**
     * @var array
     */
    protected $adapterList = array();

    /**
     * @var array
     */
    protected $params = array();

    /**
     * @var string
     */
    protected $templateClass = '\Dom\Template';



    /**
     *
     *
     *
     */
    public function __constructor()
    {

    }

    /**
     * Get a single instance of this object
     *
     * @param string $class
     * @return Loader
     */
    static function getInstance($class = '', $params = null)
    {
        if (static::$instance == null) {
            static::$instance = new static();
            static::$instance->addAdapter(new Loader\Adapter\DefaultLoader());
        }
        if (!$class) {
            $class = self::getTraceClass(debug_backtrace());
        }
        if ($params !== null) {
            static::$instance->params = $params;
        }
        static::$instance->calledClass = $class;
        return static::$instance;
    }

    /**
     * Load an xml/xhtml strings
     *
     * @param string $xhtml
     * @param string $class
     * @return Template
     */
    static function load($xhtml, $class = '')
    {
        if (!$class)
            $class = self::getTraceClass(debug_backtrace());
        return self::getInstance($class)->doLoad($xhtml);
    }

    /**
     * Load an xml/xhtml file
     *
     * @param string $path
     * @param string $class
     * @return Template
     */
    static function loadFile($path, $class = '')
    {
        if (!$class)
            $class = self::getTraceClass(debug_backtrace());
        return self::getInstance($class)->doLoadFile($path);
    }

    /**
     * @param $trace
     * @return mixed
     */
    static private function getTraceClass($trace)
    {
        $caller = $trace[1];
        return $caller['class'];
    }


    /**
     * Load an xml/xhtml strings
     *
     * @param $xhtml
     * @return Template
     */
    public function doLoad($xhtml)
    {
        /** @var Loader\Adapter\Iface $adapter */
        foreach($this->adapterList as $adapter) {
            $tpl = $adapter->load($xhtml, $this->calledClass);
            if ($tpl instanceof Template) {
                return $tpl;
            }
        }
    }

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @return Template
     */
    public function doLoadFile($path)
    {
        /** @var Loader\Adapter\Iface $adapter */
        foreach($this->adapterList as $adapter) {
            $tpl = $adapter->loadFile($path, $this->calledClass);
            if ($tpl instanceof Template) {
                return $tpl;
            }
        }
    }




    /**
     * Get the templateClass to use
     *
     * @return string
     */
    public function getTemplateClass()
    {
        return $this->templateClass;
    }

    /**
     * Set the template class if different from \Dom\Template
     * If you change this the new template object must
     * inherit/implement the \Dom\Template class
     *
     * @param string $templateClass
     * @return $this
     * @throws Exception
     */
    public function setTemplateClass($templateClass)
    {
        if (!class_exists($templateClass))
            throw new Exception('Template Class not found: ' . $templateClass);
        $this->templateClass = $templateClass;
        return $this;
    }

    /**
     * addAdapter
     *
     * Adds an adapter to the beginning of the array
     *
     * @param Loader\Adapter\Iface $adapter
     * @return Loader\Adapter\Iface
     */
    public function addAdapter(Loader\Adapter\Iface $adapter)
    {
        $adapter->setLoader($this);
        array_unshift($this->adapterList, $adapter);
        return $adapter;
    }

    /**
     * @return $this
     */
    public function resetAdapterList()
    {
        $this->adapterList = array();
        return $this;
    }

    /**
     * @return string
     */
    public function getCalledClass()
    {
        return $this->calledClass;
    }

    /**
     * Get a value from the params list if it exist
     *
     * @param string $key
     * @return mixed
     */
    public function getParam($key)
    {
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

}