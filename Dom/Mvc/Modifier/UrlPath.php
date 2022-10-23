<?php
namespace Dom\Mvc\Modifier;


/**
 * Convert Urls to Template relative and project relative
 *
 * This filter assumes that the paths are used as follows:
 *
 * Template Relative: <img src="./img/image.png" />     {TODO: I think this can be removed}
 *   The path prefix './' will be treated as a special case and be
 *   converted to the root of the current page template folder so an example
 *   in this case if the template path was in '/html/default' the converted path
 *   would be <img src="/html/default/img/image.png" />
 *   All links in pages even in sub paths would be converted
 *   <img src="./docs/api/image.png" />  TO  <img src="/html/default/docs/api/image.png" />
 *   <img src="./image.png" />  TO  <img src="/html/default/image.png" />
 *
 * Absolute: <img src="/img/image.png" />, <img src="img/image.png" />
 *   For absolute paths we assume the template is accessing
 *   assets from the project base. So a path of "/js/image.png"
 *   will be converted to "{projectPath}/js/image.png".
 *   Assuming the project baseUrl is '/project'
 *   <img src="image.png" />  TO  <img src="/project/image.png" />
 *   <img src="/img/image.png" />  TO  <img src="/project/img/image.png" />
 *
 * The filter attempts to convert some javascript event attribute paths but it is expected
 * that the designer uses javascript to config paths using the project code.
 *  TODO:
 *       We need to refactor this and only replace full paths:
 *       <img src="/img/image.png" />  TO  <img src="/project/img/image.png" />
 *       All relative and template URLS can be removed...
 *
 * @author Tropotek <https://www.tropotek.com/>
 */
class UrlPath extends FilterInterface
{

    /**
     * Att his to element nodes that you want to ignore replacing URL's
     */
    public static string $ATTR_IGNORE_REL = 'data-ignore-rel';

    /**
     * element attributes to search for path URL`s
     */
    protected array $attrSrc = [
        'src', 'href', 'action', 'background',  // standard attributes
        'data-href', 'data-src', 'data-url'     // Custom data attributes
    ];

    /**
     * Javascript attributes to search for URL`s
     */
    protected array $attrJs = ['onmouseover', 'onmouseup', 'onmousedown', 'onmousemove', 'onmouseover', 'onclick'];

    /**
     * The site base file path
     */
    protected string $basePath = '';

    /**
     * The site base Url path
     */
    protected string $baseUrl = '';



    /**
     * __construct
     */
    public function __construct(string $basePath = '', string $baseUrl = '')
    {
        $this->basePath    = rtrim($basePath, '/');
        $this->baseUrl     = rtrim($baseUrl, '/');
    }

    /**
     * pre init the filter
     */
    public function init(\DOMDocument $doc) { }

    /**
     * Execute code on the current Node
     */
    public function executeNode(\DOMElement $node)
    {
        /** @var \DOMAttr $attr */
        foreach ($node->attributes as $attr) {
            if (in_array(strtolower($attr->nodeName), $this->attrSrc)) {
                // Replace '#' only URI`s because of the double redirect bug on some browsers
                if ($attr->value == '#') {
                    $attr->value = 'javascript:;';
                    continue;
                }

                // Disable conversion of nodes with self::$ATTR_IGNORE_REL attribute
                $noRel = false;
                foreach ($node->attributes as $a) {
                    if ($a->nodeName == self::$ATTR_IGNORE_REL) $noRel = true;
                }
                if ($noRel) continue;

                // And start of URL  matched existing dev path, then ignore.
                // Temp fix to stop conversion of WYSIWYG links in debug mode.
                if ($this->isDebug() && !empty(rtrim($this->baseUrl, '/')) && substr($attr->value, 0, strlen($this->baseUrl)) === $this->baseUrl) {
                    continue;
                }

                if (
                    preg_match('/^#/', $attr->value) ||     // ignore fragment urls
                    preg_match('/(\S+):(\S+)/', $attr->value) || preg_match('/^\/\//', $attr->value)   // ignore full and application URI`s
                ) {
                    continue;
                }
                $attr->value = htmlentities($this->prependPath($attr->value));
            } elseif (in_array(strtolower($attr->nodeName), $this->attrJs)) {       // replace javascript strings
                $attr->value = htmlentities($this->replaceStr($attr->value));
            } elseif (strtolower($attr->nodeName) == 'style') {
                if (preg_match_all('/url\(.*\)/', $attr->nodeValue)) {
                    $newValue = preg_replace_callback('/url\(((\"|\'|)?.*[\'"]?)\)/U', function ($matches) {
                        $url = "'" . $this->prependPath(str_replace(array('"',"'"), '', $matches[1])) . "'";
                        return 'url('.$url.')';
                    }, $attr->nodeValue);
                    $attr->nodeValue = $newValue;
                }
                // TODO: possibly do the same to inline style tag URL's
            }
        }
    }

    /**
     * Execute code on the current Comment Node
     */
    public function executeComment(\DOMComment $node)
    {
        $node->data = $this->replaceStr($node->data);
    }


    /**
     * Add a custom src url attribute
     */
    public function addUrlAttr(string $attr): UrlPath
    {
        if (!in_array($attr, $this->attrSrc))
            $this->attrSrc[] = $attr;
        return $this;
    }

    /**
     * Prepend the path to a relative link
     *
     * Eg:
     *      $path = /path/to/resource.js
     *      return /site/root/path/to/resource.js
     */
    private function prependPath(string $path): string
    {
        if (!$path) return $path;
        return $this->cleanUrl($path, $this->baseUrl);
    }

    /**
     * Clean a path so that there is no duplication of any path prefixes
     */
    protected function cleanUrl(string $url, string $replace = ''): string
    {
        $url = rtrim($url, '/');
        if ($replace) {
            $fixedPath = preg_replace('/^' . preg_quote($replace, '/') . '/', '', $url);
            if ($fixedPath) {
                $url = $fixedPath;
            }
        }

        if (!$url) $url = '/';
        $processedUrl = \Tk\Uri::create($url)->toString();
        if ($url && preg_match('/^\.?(\/|\/)(.+)/', $url, $regs)) {
            $url = '/' . $regs[2];
            $processedUrl = \Tk\Uri::create($replace . $url)->toString();
        }
        return $processedUrl;
    }

    /**
     * replace a string with paths using string replace.
     * Useful for urls in script text and comments.
     */
    protected function replaceStr(string $str): string
    {
        $str = str_replace('{siteUrl}', $this->baseUrl, $str);
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
     * @throws \Tk\Exception
     */
    private function cleanRelative(string $path): string
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

    public function isDebug(): bool
    {
        return (class_exists('\Tk\Config') && \Tk\Config::instance()->isDebug());
    }
}