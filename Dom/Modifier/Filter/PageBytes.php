<?php
namespace Dom\Modifier\Filter;

use Dom\Modifier\Exception;

/**
 * This class is meant to be an indicator of sizes not an exact measurement
 *
 * For example any styles loaded using @import or flies loaded by dynamic javascript
 * will not be calculated.
 *
 * Also no image sizes are calculated.
 *
 */
class PageBytes extends Iface
{

    /**
     * @var int
     */
    private $cssTotal = 0;

    /**
     * @var int
     */
    private $jsTotal = 0;

    /**
     * @var int
     */
    private $htmlTotal = 0;

    /**
     * @var array
     */
    private $checkedHash = array();


    protected $baseUrl = '';
    protected $baseUrlPath = '';

    protected $basePath = '';



    /**
     * __construct
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->baseUrl = rtrim(\Tk\Uri::create('/')->toString(), '/');
        $this->baseUrlPath = rtrim(\Tk\Uri::create('/')->getPath(), '/');
    }

    function getCssBytes()
    {
        return $this->cssTotal;
    }

    function getJsBytes()
    {
        return $this->jsTotal;
    }

    function getHtmlBytes()
    {
        return $this->htmlTotal;
    }

    /**
     * pre init the Filter
     *
     * @param \DOMDocument $doc
     * @throws Exception
     */
    public function init($doc)
    {

    }

    /**
     * pre init the Filter
     *
     * @param \DOMDocument $doc
     * @throws \Exception
     *
     */
    public function postTraverse($doc)
    {
        $str = $doc->saveXML();
        if ($str) {
            $this->htmlTotal = \Tk\File::string2Bytes(strlen($str));
        }
    }

    private function url2path($url)
    {
        //vd($this->baseUrl, $this->baseUrlPath);
        if (preg_match('/^' . preg_quote($this->baseUrl, '/') . '/', $url)) {
            $url = preg_replace('/^' . preg_quote($this->baseUrl, '/') . '/', '', $url);
        }
        if (preg_match('/^' . preg_quote($this->baseUrlPath, '/') . '/', $url)) {
            $url = preg_replace('/^' . preg_quote($this->baseUrlPath, '/') . '/', '', $url);
        }
        if ($url[0] != '/' && $url[0] != '\\') $url = '/'.$url;
        return $this->basePath . $url;
    }

    /**
     * Call this method to traverse a document
     *
     * @param \DOMElement $node
     * @throws Exception
     */
    public function executeNode(\DOMElement $node)
    {
        try {
            $str = '';
            if ( $node->nodeName == 'script') {
                if ($node->hasAttribute('src') && preg_match('/\.(js)$/', $node->getAttribute('src'))) {
                    $path = $this->url2path($node->getAttribute('src'));
                    if (is_file($path)) {
                        $str = @file_get_contents($path);
                    }
                } else if (!$node->hasAttribute('src')) {
                    $str = $node->nodeValue;
                }
                $hash = md5($str);
                if ($str && !in_array($hash, $this->checkedHash)) {
                    $this->jsTotal += \Tk\File::string2Bytes(strlen($str));
                }
                $this->checkedHash[] = $hash;
                $str = null;
                return;
            }
            if ($node->nodeName == 'style') {
                $str = $node->nodeValue;
                $hash = md5($str);
                if ($str && !in_array($hash, $this->checkedHash)) {
                    $this->cssTotal += \Tk\File::string2Bytes(strlen($str));
                }
                $this->checkedHash[] = $hash;
                $str = null;
                return;
            }
            if ( $node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.(css)$/', $node->getAttribute('href'))) {
                $path = $this->url2path($node->getAttribute('href'));
                if (is_file($path)) {
                    $str = @file_get_contents($path);
                }
                $hash = md5($str);
                if ($str && !in_array($hash, $this->checkedHash)) {
                    $this->cssTotal += \Tk\File::string2Bytes(strlen($str));
                }
                $this->checkedHash[] = $hash;
                $str = null;
                return;
            }
            $str = null;
        } catch (\Exception $e) {}

    }

    /**
     * @return string
     */
    public function toString()
    {
        $str = '';
        $j = $this->getJsBytes();
        $c = $this->getCssBytes();
        $h = $this->getHtmlBytes();
        $t = $j + $c +$h;

        $str .= '------- Page Totals -------' . \PHP_EOL;
        $str .= sprintf('JS:      %6s   %6s b', \Tk\File::bytes2String($j), $j) . \PHP_EOL;
        $str .= sprintf('CSS:     %6s   %6s b', \Tk\File::bytes2String($c), $c) . \PHP_EOL;
        $str .= sprintf('HTML:    %6s   %6s b', \Tk\File::bytes2String($h), $h) . \PHP_EOL;
        $str .= '---------------------------' . \PHP_EOL;
        $str .= sprintf('TOTAL:   %6s   %6s b', \Tk\File::bytes2String($t), $t) . \PHP_EOL;
        $str .= '---------------------------';
        return $str;
    }

    public function __toString()
    {
        return $this->toString();
    }


}
