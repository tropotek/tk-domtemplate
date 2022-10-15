<?php
namespace Dom\Mvc\Loader;

use Dom\Exception;
use \Dom\Template;

/**
 * This adapter will look for template files in the supplied
 * paths using the class name with underscores or slashes depending on the $useUnderscores param.
 *
 * For example if the calling class is App\Controller\Home then the default path would be:
 *   - App\Controller\Home => {$basePath} . '/App/Controller/Home.xtpl'
 *
 * If the $useUnderscores value is true then the path would be:
 *  - App\Controller\Home => {$basePath} . '/App_Controller_Home.xtpl'
 *
 * <code>
 *     $loader = new Loader($this->getEventDispatcher());
 *     $path = $this->getConfig()->getBasePath() . '/html/templates';
 *     $loader->addAdapter(new Loader\DefaultAdapter());
 *     $loader->addAdapter(new Loader\ClassPathAdapter($path));
 *     ..
 *     $template = $this->getFactory()->getTemplateLoader()->loadFile();
 *     return $template->toString();
 * </code>
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class ClassPathAdapter extends AdapterInterface
{
    /**
     * The default path would is:
     *    \App\Controller\Index   =>  {path}/App/Controller/index.{extension}
     *
     * If $useUnderscores is true then the search path will be a single class filename:
     * EG:
     *    \App\Controller\Index   =>  {path}/App_Controller_index.{extension}
     *
     */
    protected bool $useUnderscores = false;

    protected string $extension = 'xtpl';

    protected string $basePath = '';


    public function __construct(string $basePath, string $extension = 'xtpl', bool $useUnderscores = false)
    {
        $this->basePath = $basePath;
        $this->extension = trim($extension, '.');
        $this->useUnderscores = $useUnderscores;
    }

    /**
     * Load xml/xhtml string template
     *
     * @throws Exception
     */
    public function load(string $xhtml = ''): ?Template
    {
        $tpl =  $this->loadFile();
        if (!$tpl) return Template::load($xhtml);
    }

    /**
     * Load xml/xhtml file template
     * If no path value is passed then a path is created by using the calling class
     *
     * @throws Exception
     */
    public function loadFile(string $path = ''): ?Template
    {
        if (!$path) {
            $class = $this->getCallingClass();
            $classPath = str_replace('\\', '/', $class);
            if ($this->useUnderscores) {
                $classPath = str_replace('\\', '_', $class);
            }
            $path = $this->basePath . '/' . trim($classPath, '/') . '.' . $this->extension;
        }
        if (!is_file($path)) return null;
        return Template::loadFile($path);
    }

    protected function getCallingClass(int $skip = 3): string
    {
        $trace = debug_backtrace();
        $caller = $trace[$skip] ?? [];
        if (!empty($caller['class'])) {
            return $caller['class'];
        }
        if (!empty($caller['object'])) {
            return get_class($caller['object']);
        }
        return '';
    }

}