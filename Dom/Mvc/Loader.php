<?php
namespace Dom\Mvc;

use Dom\Mvc\Event\LoadEvent;
use Dom\Mvc\Loader\AdapterInterface;
use Dom\Template;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Template Loader
 *
 * Use this class to facilitate automatic searching loading of template files
 *
 * You can add loader adapters to find templates
 *
 * NOTE: Adapters are run in a LIFO (Last In First Out) queue.
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class Loader
{

    /**
     * @var array|AdapterInterface[]
     */
    protected array $adapterList = [];

    protected ?EventDispatcherInterface $dispatcher = null;


    public function __construct(?EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Load xml/xhtml string template
     */
    public function load(string $xhtml = ''): ?Template
    {
        foreach($this->adapterList as $adapter) {
            $tpl = $adapter->load($xhtml);
            if ($tpl instanceof Template) {
                return $this->triggerLoadEvent($tpl, $adapter);
            }
        }
        return null;
    }

    /**
     * Load xml/xhtml file template
     */
    public function loadFile(string $path = ''): ?Template
    {
        foreach($this->adapterList as $adapter) {
            $tpl = $adapter->loadFile($path);
            if ($tpl instanceof Template) {
                return $this->triggerLoadEvent($tpl, $adapter);
            }
        }
        return null;
    }

    protected function triggerLoadEvent(Template $template, AdapterInterface $adapter): Template
    {
        if ($this->dispatcher) {
            $this->dispatcher->dispatch(new LoadEvent($template, $adapter));
        }
        return $template;
    }

    /**
     * Adds an adapter to the beginning of the array
     *
     * NOTE: Adapters are run in a LIFO (Last In First Out) queue.
     * @see https://en.wikipedia.org/wiki/LIFO_%28education%29
     */
    public function addAdapter(AdapterInterface $adapter): AdapterInterface
    {
        $adapter->setLoader($this);
        array_unshift($this->adapterList, $adapter);
        return $adapter;
    }

    public function getAdapterList(): array
    {
        return $this->adapterList;
    }

}