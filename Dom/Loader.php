<?php
namespace Dom;

/**
 * Class Loader
 * Use this class to facilitate with the loading of template files
 *
 * You can add loader adapters to find templates in a cascading array
 *
 * NOTE: Adapters are run as as LIFO (Last In First Out) queue.
 * @link https://en.wikipedia.org/wiki/LIFO_%28education%29
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
    protected $callingClass = '';

    /**
     * @var array
     */
    protected $adapterList = array();

    /**
     * @var array
     */
    protected $params = array();


    /**
     * 
     */
    private function __constructor() { }


    /**
     * Get a single instance of this object
     *
     * @param string $class
     * @param null|array $params
     * @return Loader
     */
    static function getInstance($class = '', $params = null)
    {
        if (static::$instance == null) {
            static::$instance = new static();
        }
        if (!$class) {
            $class = self::getTraceClass(debug_backtrace());
        }
        if ($params !== null) {
            static::$instance->params = $params;
        }
        static::$instance->callingClass = $class;
        return static::$instance;
    }

    /**
     * Load an xml/xhtml strings
     *
     * @param string $xhtml
     * @param string $callingClass
     * @return Template
     * @throws Exception
     */
    static function load($xhtml, $callingClass = '')
    {        
        if (!$callingClass)
            $callingClass = self::getTraceClass(debug_backtrace());
        $tpl = self::getInstance($callingClass)->doLoad($xhtml);
        if (!$tpl) {
            throw new Exception('Unknown error, Cannot load template. (' . substr($xhtml, 0, 20) . ', ' . $callingClass . ')');
        }
        return $tpl;
    }

    /**
     * Load an xml/xhtml file
     *
     * @param string $path
     * @param string $callingClass
     * @return Template
     * @throws Exception
     */
    static function loadFile($path, $callingClass = '')
    {
        if (!$callingClass)
            $callingClass = self::getTraceClass(debug_backtrace());
        $tpl = self::getInstance($callingClass)->doLoadFile($path);
        if (!$tpl) {
            throw new Exception('Unknown error, Cannot load template. ('.$path.', '.$callingClass.')');
        }
        return $tpl;
    }

    /**
     * @param $trace
     * @return mixed
     */
    static private function getTraceClass($trace)
    {
        $caller = $trace[1];
        //vd($caller);
        if (!empty($caller['object'])) {
            return get_class($caller['object']);
        }
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
            $tpl = $adapter->load($xhtml, $this->callingClass);
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
            $tpl = $adapter->loadFile($path, $this->callingClass);
            if ($tpl instanceof Template) {
                return $tpl;
            }
        }
    }

    /**
     * addAdapter
     *
     * Adds an adapter to the beginning of the array
     *
     * NOTE: Adapters are run as as LIFO (Last In First Out) queue.
     * @link https://en.wikipedia.org/wiki/LIFO_%28education%29
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
     * @return array
     */
    public function getAdapterList()
    {
        return $this->adapterList;
    }

    /**
     * @param array $adapterList
     * @return $this
     */
    public function setAdapterList($adapterList)
    {
        $this->adapterList = $adapterList;
        return $this;
    }


    /**
     * @return string
     */
    public function getCallingClass()
    {
        return $this->callingClass;
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
     * @param array|\ArrayAccess $params
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

}