<?php
namespace Dom\Modifier\Filter;

/**
 * Convert all template relative paths to full path url's
 *
 * For all template paths we need to rewrite all the asset paths.
 *
 * This filter assumes that the paths are used as follows:
 *
 * Relative: All relative paths as in <a href="js/image.png">image</a>
 *   will be converted to use the full template path so this path will
 *   be converted to "/projectPath/templatePath/js/image.png"
 *
 * Absolute: For absolute paths we assume the template is accessing
 *   assets from the project root. So a path of "/js/image.png"
 *   will be converted to "/projectPath/js/image.png" and the template
 *   path is ignored.
 *
 * This filter attempts to convert some javascript paths but it is expected
 * that the designer uses the javascript config object (if available) to
 * build the asset paths within the javascript code.
 *
 * NOTE: js and css files are not modified by this filter only the
 *   DomTemplate object is handled.
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class Path extends Iface
{

    /**
     * @var array
     */
    protected $attrSrc = array(
        // Custom data attributes
        'src', 'href', 'action', 'background',
        // Custom data attributes
        'data-href', 'data-src', 'data-url'
    );

    /**
     * @var array
     */
    protected $attrJs = array('onmouseover', 'onmouseup', 'onmousedown', 'onmousemove', 'onmouseover', 'onclick');

    /**
     * The site root file path
     * @var string
     */
    protected $siteUrl = '';

    /**
     * The site root file path
     * @var string
     */
    protected $templateUrl = '';



    /**
     * __construct
     *
     * @param string $siteUrl
     * @param string $templateUrl
     */
    public function __construct($siteUrl = '', $templateUrl = '')
    {
        $this->siteUrl = $siteUrl;
        $this->templateUrl = $templateUrl;
    }


    /**
     * Prepend the site document root url to the provided path
     *
     * Eg:
     *      $path = /path/to/resource.js
     *      return /site/root/path/to/resource.js
     *
     * @param string $path
     * @return string
     */
    protected function addSiteUrl($path)
    {
        $path = str_replace($this->siteUrl, '', $path);
        $retPath = $this->siteUrl . $path;
        return $retPath;
    }

    /**
     * Prepend the theme document root to the provided path
     *
     * Eg:
     *      $path = path/to/resource.js
     *      $path = ./path/to/resource.js
     *      $path = ../path/to/resource.js
     *      $path = ../../path/to/resource.js
     *      return /site/root/theme/selected/path/to/resource.js
     *
     * @param string $path
     * @return string
     */
    protected function addThemeUrl($path)
    {
        $path = str_replace($this->templateUrl, '', $path);
        $retPath =  $this->templateUrl . '/' . $path;
        return $retPath;
    }

    /**
     * replace a string with paths using string replace.
     * Useful for urls in script text and comments.
     *
     * @param $str
     * @return mixed
     */
    protected function replaceStr($str)
    {
        $str = str_replace('{sitePath}', $this->siteUrl, $str);
        $str = str_replace('{siteUrl}', $this->siteUrl, $str);
        $str = str_replace('{themePath}', $this->templateUrl, $str);
        $str = str_replace('{themeUrl}', $this->templateUrl, $str);

        return $str;
    }

    /**
     * Clean a path from ./ ../ but keep path integrity.
     * eg:
     *
     *   From: /Work/Projects/tk003-trunk/theme/default/../../../../relative/path/from/theme.html
     *     To: /Work/relative/path/from/theme.html
     *
     * Note: This function can give access to unwanted paths if not used carfully.
     *
     * @param string $path
     * @return string
     */
    private function cleanRelative($path)
    {
        // TODO: could cause security issues. see how we go without it.
        $path = str_replace(array('//','\\\\'), array('/','\\'), $path);
        $array = explode( '/', $path);
        $parents = array();
        foreach( $array as $dir) {
            switch( $dir) {
                case '.':
                    // Don't need to do anything here
                    break;
                case '..':
                    array_pop( $parents);
                    break;
                default:
                    $parents[] = $dir;
                    break;
            }
        }
        return implode( '/', $parents);
    }

    /**
     * pre init the front controller
     *
     * @param \DOMDocument $doc
     */
    public function init($doc)
    {
        // Try to automatically determin the template path
        if (!$this->templateUrl && $doc->documentURI) {
            $config = \Tk\Config::getInstance();
            $urlStr = str_replace($config->getAppPath(), '', $doc->documentURI);
            $urlStr = dirname($urlStr);
            $urlStr = $config->getAppUrl() . $urlStr;
            $this->templateUrl = $urlStr;
        }
    }

    /**
     * Call this method to travers a document
     *
     * @param \DOMComment $node
     */
    public function executeComment(\DOMComment $node)
    {
        $node->data = $this->replaceStr($node->data);
    }

    /**
     * Call this method to travers a document
     *
     * @param \DOMElement $node
     */
    public function executeNode(\DOMElement $node)
    {
        // Modify local paths to full path url's
        foreach ($node->attributes as $attr) {
            if (in_array(strtolower($attr->nodeName), $this->attrSrc)) {
                if (preg_match('/^#$/', $attr->value)) { // ignore hash urls
                    $attr->value = 'javascript:;';
                    continue;
                }
                if (preg_match('/^#/', $attr->value)) { // ignore fragment urls
                    continue;
                }
                if (preg_match('/(\S+):(\S+)/', $attr->value) || preg_match('/^\/\//', $attr->value)) {   // ignore full urls and schema-less urls
                    continue;
                }
                $attr->value = htmlentities($this->prependPath($attr->value));
            } elseif (in_array(strtolower($attr->nodeName), $this->attrJs)) {       // replace javascript strings
                $attr->value = htmlentities($this->replaceStr($attr->value));
            }
        }
    }

    /**
     * Prepend the path to a relative link on the page
     *
     *
     * @param string $path
     * @return string
     */
    private function prependPath($path)
    {
        if (!$path) return $path;
        if ($path[0] == '/' || $path[0] == '\\') {   // match site relative paths
            $retPath = $this->addSiteUrl($path);
        } else  {
            $retPath = $this->addThemeUrl($path);
        }
        return $retPath;
    }

}