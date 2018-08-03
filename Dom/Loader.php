<?php
namespace Dom;

/**
 * Class Loader
 * Use this class to facilitate with the loading of template files
 *
 * You can add loader adapters to find templates in a cascading array
 *
 * NOTE: Adapters are run as as LIFO (Last In First Out) queue.
 * @see https://en.wikipedia.org/wiki/LIFO_%28education%29
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Loader
{

    /**
     * @var Loader
     */
    public static $instance = null;

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
     * @var null|\Tk\Event\Dispatcher
     */
    protected $dispatcher = null;


    /**
     * @param null|\Tk\Event\Dispatcher $dispatcher
     */
    protected function __construct($dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }


    /**
     * Get a single instance of this object
     *
     * @param string $class
     * @param null|array $params
     * @return Loader
     */
    public static function getInstance($class = '', $params = null)
    {
        if (static::$instance == null) {
            $dispatcher = null;
            if (class_exists('App\Config')) {
                $dispatcher = \App\Config::getInstance()->getEventDispatcher();
            }
            static::$instance = new static($dispatcher);
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
     */
    public static function load($xhtml, $callingClass = '')
    {        
        if (!$callingClass)
            $callingClass = self::getTraceClass(debug_backtrace());
        $tpl = self::getInstance($callingClass)->doLoad($xhtml);
        return $tpl;
    }

    /**
     * Load an xml/xhtml file
     *
     * @param string $path
     * @param string $callingClass
     * @return Template
     */
    public static function loadFile($path, $callingClass = '')
    {
        if (!$callingClass)
            $callingClass = self::getTraceClass(debug_backtrace());
        $tpl = self::getInstance($callingClass)->doLoadFile($path);
        return $tpl;
    }

    /**
     * @param $trace
     * @return mixed
     */
    private static function getTraceClass($trace)
    {
        $caller = $trace[1];
        if (!empty($caller['object'])) {
            return get_class($caller['object']);
        }
        return $caller['class'];
    }


    /**
     * Load an xml/xhtml strings
     *
     * @param $xhtml
     * @return null|Template
     */
    public function doLoad($xhtml)
    {
        if (!$this->adapterList || !count($this->adapterList)) {
            \Tk\Log::notice('No Template loaders defined!');
            return null;
        }
        /* @var Loader\Adapter\Iface $adapter */
        foreach($this->adapterList as $adapter) {
            if ($adapter instanceof Loader\Adapter\DefaultLoader) continue;
            $tpl = $adapter->load($xhtml, $this->callingClass);
            if ($tpl instanceof Template) {
                $tpl = $this->triggerLoadEvent($tpl);
                return $tpl;
            }
        }
        $adapter = new \Dom\Loader\Adapter\DefaultLoader();
        $tpl = $adapter->load($xhtml, $this->callingClass);
        if ($tpl instanceof Template) {
            $tpl = $this->triggerLoadEvent($tpl);
            return $tpl;
        }
        return null;
    }

    /**
     * Load an xml/xhtml file
     *
     * @param $path
     * @return Template
     */
    public function doLoadFile($path)
    {
        if (!$this->adapterList || !count($this->adapterList)) {
            \Tk\Log::notice('No Template loaders defined!');
            return null;
        }
        /* @var Loader\Adapter\Iface $adapter */
        foreach($this->adapterList as $adapter) {
            if ($adapter instanceof Loader\Adapter\DefaultLoader) continue;
            $tpl = $adapter->loadFile($path, $this->callingClass);
            if ($tpl instanceof Template) {
                $tpl = $this->triggerLoadEvent($tpl);
                return $tpl;
            }
        }
        $adapter = new \Dom\Loader\Adapter\DefaultLoader();
        $tpl = $adapter->loadFile($path, $this->callingClass);
        if ($tpl instanceof Template) {
            $tpl = $this->triggerLoadEvent($tpl);
            return $tpl;
        }
        return null;
    }

    /**
     * @param Template $template
     * @return Template
     */
    protected function triggerLoadEvent($template)
    {
        if ($this->dispatcher) {
            $e = new \Dom\Event\DomEvent($template);
            $e->set('callingClass', $this->getCallingClass());
            $this->dispatcher->dispatch(\Dom\DomEvents::DOM_TEMPLATE_LOAD, $e);
        }
        return $template;
    }

    /**
     * addAdapter
     *
     * Adds an adapter to the beginning of the array
     *
     * NOTE: Adapters are run as as LIFO (Last In First Out) queue.
     * @see https://en.wikipedia.org/wiki/LIFO_%28education%29
     *
     * @param Loader\Adapter\Iface $adapter
     * @return Loader\Adapter\Iface
     */
    public function addAdapter(Loader\Adapter\Iface $adapter)
    {
        if ($adapter instanceof Loader\Adapter\DefaultLoader) return $adapter;
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
        return '';
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