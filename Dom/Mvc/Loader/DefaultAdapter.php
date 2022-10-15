<?php
namespace Dom\Mvc\Loader;

use Dom\Exception;
use Dom\Mvc\Loader\AdapterInterface;
use \Dom\Template;

/**
 * Default adapter for the loader object.
 *
 * This should be run last after all other adapters have been tried
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class DefaultAdapter extends AdapterInterface
{

    /**
     * Load xml/xhtml string template
     *
     * @throws Exception
     */
    public function load(string $xhtml = ''): ?Template
    {
        return Template::load($xhtml);
    }

    /**
     * Load xml/xhtml file template
     *
     * @throws Exception
     */
    public function loadFile($path = ''): ?Template
    {
       return Template::loadFile($path);
    }

}