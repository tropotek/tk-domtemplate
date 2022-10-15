<?php
namespace Dom\Mvc\Loader;

use \Dom\Mvc\Loader;
use Dom\Template;

/**
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
abstract class AdapterInterface
{

    protected ?Loader $loader = null;


    /**
     * Load xml/xhtml string template
     */
    abstract public function load(string $xhtml = ''): ?Template;

    /**
     * Load xml/xhtml file template
     */
    abstract public function loadFile(string $path = ''): ?Template;


    public function getLoader(): Loader
    {
        return $this->loader;
    }

    public function setLoader(Loader $loader)
    {
        $this->loader = $loader;
    }
}