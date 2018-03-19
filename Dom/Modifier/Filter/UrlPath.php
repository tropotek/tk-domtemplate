<?php
namespace Dom\Modifier\Filter;

/**
 * Convert Urls to Template relative and project relative
 *
 * This filter assumes that the paths are used as follows:
 *
 * Template Relative: <img src="./img/image.png" />
 *   The path prefix './' will be trated as a special case and be
 *   converted to the root of the current p[age template folder so an example
 *   in this case if the template path was in '/html/default' the converted path
 *   would be <img src="/html/default/img/image.png" />
 *   All links in pages even in sub paths would be converted
 *   <img src="./docs/api/image.png" />  TO  <img src="/html/default/docs/api/image.png" />
 *   <img src="./image.png" />  TO  <img src="/html/default/image.png" />
 *
 * Absolute: <img src="/img/image.png" />, <img src="img/image.png" />
 *   For absolute paths we assume the template is accessing
 *   assets from the project root. So a path of "/js/image.png"
 *   will be converted to "{projectPath}/js/image.png" and the template
 *   path is ignored.
 *   Assuming the project URL path is 'http://example.com/project'
 *   <img src="image.png" />  TO  <img src="http://example.com/project/image.png" />
 *   <img src="/img/image.png" />  TO  <img src="http://example.com/project/img/image.png" />
 *
 * The filter attempts to convert some javascript event attribute paths but it is expected
 * that the designer uses javascript to config paths using the project code.
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 */
class UrlPath extends Iface
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
     * @param string|\Tk\Uri $siteUrl
     * @param string|\Tk\Uri $templateUrl
     */
    public function __construct($siteUrl = '', $templateUrl = '')
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->templateUrl = rtrim($templateUrl, '/');
    }

    /**
     * Add a custom src url attribute
     *
     * @param $attr
     * @return $this
     */
    public function addUrlAttr($attr)
    {
        if (!in_array($attr, $this->attrSrc))
            $this->attrSrc[] = $attr;
        return $this;
    }

    /**
     * pre init the filter
     *
     * @param \DOMDocument $doc
     */
    public function init($doc)
    {
        // TODO: Remove the config object from here.......
        $config = \Tk\Config::getInstance();
        // Try to automatically determin the template path
        if (!$this->templateUrl && $doc->documentURI) {
            $urlStr = str_replace($config->getSitePath(), '', $doc->documentURI);
            $urlStr = dirname($urlStr);
            $urlStr = $config->getSiteUrl() . $urlStr;
            $this->templateUrl = $urlStr;
        }
    }

    /**
     * Execute code on the current Comment Node
     *
     * @param \DOMComment $node
     */
    public function executeComment(\DOMComment $node)
    {
        $node->data = $this->replaceStr($node->data);
    }

    /**
     * Execute code on the current Node
     *
     * @param \DOMElement $node
     */
    public function executeNode(\DOMElement $node)
    {
        // Modify local paths to full path url's
        foreach ($node->attributes as $attr) {
            if (in_array(strtolower($attr->nodeName), $this->attrSrc)) {
                if (preg_match('/^#$/', $attr->value)) {    // ignore hash urls
                    $attr->value = 'javascript:;';      // Because of the '#' double redirect bug on old FF browsers
                    continue;
                }
                if (
                    preg_match('/^#/', $attr->value) ||     // ignore fragment urls
                    preg_match('/(\S+):(\S+)/', $attr->value) || preg_match('/^\/\//', $attr->value) ||   // ignore full urls and schema-less urls
                    preg_match('/^[a-z0-9]{1,10}:/', $attr->value)  // ignore Full URL's
                ) {
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
        if (preg_match('|^\.\/|', $path)) {
            $retPath = $this->addTemplateUrl($path);
        } else {
            $retPath = $this->addSiteUrl($path);
        }
        return $retPath;
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
        $path = rtrim($path, '/');
        $searchFor = \Tk\Uri::create($this->siteUrl)->getPath();
        $fixedPath = preg_replace('/^'.preg_quote($searchFor, '/').'/', '', $path);
        if ($fixedPath) {
            $path = $fixedPath;
        }
        if ($path && $path[0] != '/' && $path[0] != '\\') $path = '/'.$path;
        $path = preg_replace('/^\/\.\//', '/', $path);
        $url = \Tk\Uri::create($this->siteUrl . $path)->toString();
        return $url;
    }

    /**
     * Prepend the theme document root to the provided path
     *
     * Eg:
     *      $path = path/to/resource.js
     *      $path = ./path/to/resource.js
     *      $path = ../path/to/resource.js
     *      $path = ../../path/to/resource.js
     *      return /site/root/template/selected/path/to/resource.js
     *
     * @param string $path
     * @return string
     */
    protected function addTemplateUrl($path)
    {
        $path = rtrim($path, '/');
        $searchFor = \Tk\Uri::create($this->templateUrl)->getPath();
        $fixedPath = preg_replace('/^'.preg_quote($searchFor, '/').'/', '', $path);
        if ($fixedPath) {
            $path = $fixedPath;
        }
        if ($path[0] != '/' && $path[0] != '\\') $path = '/'.$path;
        $path = preg_replace('/^\/\.\//', '/', $path);
        $url = \Tk\Uri::create($this->templateUrl . $path)->toString();
        return $url;
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
        $str = str_replace('{siteUrl}', $this->siteUrl, $str);
        $str = str_replace('{templateUrl}', $this->templateUrl, $str);

        return $str;
    }


    /**
     * Clean a path from ./ ../ but keep path integrity.
     * eg:
     *
     *   From: /Work/Projects/tk003-trunk/template/default/../../../../relative/path/from/template.html
     *     To: /Work/relative/path/from/template.html
     *
     * Note: This function can give access to unwanted paths if not used carefully.
     *
     * @param string $path
     * @return string
     * @throws \Tk\Exception
     */
    private function cleanRelative($path)
    {
        if (preg_match('/^\/\//', $path)) {
            // Should not be `http://` at the start
            throw new \Tk\Exception('Invalid url path: ' . $path);
        }

        // TODO: could cause security issues. see how we go without it.
        //$path = str_replace(array('//','\\\\'), array('/','\\'), $path);
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
}