<?php
namespace Dom\Mvc\Modifier;


/**
 * This class is meant to be an indicator of sizes not an exact measurement
 *
 * For example any styles loaded using @import or flies loaded by dynamic javascript
 * will not be calculated.
 *
 * Note: No image sizes are calculated.
 * Note: Do not use in production environments.
 */
class PageBytes extends FilterInterface
{

    private int $cssTotal = 0;

    private int $jsTotal = 0;

    private int $htmlTotal = 0;

    private array $checkedHash = [];

    protected string $baseUrl = '';

    protected string $baseUrlPath = '';

    protected string $basePath = '';


    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->baseUrl = rtrim(\Tk\Uri::create('/')->toString(), '/');
        $this->baseUrlPath = rtrim(\Tk\Uri::create('/')->getPath(), '/');
    }

    function getCssBytes(): int
    {
        return $this->cssTotal;
    }

    function getJsBytes(): int
    {
        return $this->jsTotal;
    }

    function getHtmlBytes(): int
    {
        return $this->htmlTotal;
    }

    /**
     * pre init the Filter
     */
    public function init(\DOMDocument $doc) { }

    /**
     * Call this method to traverse a document
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
                    $this->jsTotal += \Tk\FileUtil::string2Bytes(strlen($str));
                }
                $this->checkedHash[] = $hash;
                $str = null;
                return;
            }
            if ($node->nodeName == 'style') {
                $str = $node->nodeValue;
                $hash = md5($str);
                if ($str && !in_array($hash, $this->checkedHash)) {
                    $this->cssTotal += \Tk\FileUtil::string2Bytes(strlen($str));
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
                    $this->cssTotal += \Tk\FileUtil::string2Bytes(strlen($str));
                }
                $this->checkedHash[] = $hash;
                $str = null;
                return;
            }
            $str = null;
        } catch (\Exception $e) {}
    }

    /**
     * called after DOM tree is traversed
     */
    public function postTraverse(\DOMDocument $doc)
    {
        $str = $doc->saveXML();
        if ($str) {
            $this->htmlTotal = \Tk\FileUtil::string2Bytes(strlen($str));
        }
    }

    private function url2path(string $url): string
    {
        if (preg_match('/^' . preg_quote($this->baseUrl, '/') . '/', $url)) {
            $url = preg_replace('/^' . preg_quote($this->baseUrl, '/') . '/', '', $url);
        }
        if (preg_match('/^' . preg_quote($this->baseUrlPath, '/') . '/', $url)) {
            $url = preg_replace('/^' . preg_quote($this->baseUrlPath, '/') . '/', '', $url);
        }
        if ($url[0] != '/' && $url[0] != '\\') $url = '/'.$url;
        return $this->basePath . $url;
    }

    public function toString(): string
    {
        $str = '';
        $j = $this->getJsBytes();
        $c = $this->getCssBytes();
        $h = $this->getHtmlBytes();
        $t = $j + $c +$h;

        $str .= '------- Page Totals -------' . \PHP_EOL;
        $str .= sprintf('JS:      %6s   %6s b', \Tk\FileUtil::bytes2String($j), $j) . \PHP_EOL;
        $str .= sprintf('CSS:     %6s   %6s b', \Tk\FileUtil::bytes2String($c), $c) . \PHP_EOL;
        $str .= sprintf('HTML:    %6s   %6s b', \Tk\FileUtil::bytes2String($h), $h) . \PHP_EOL;
        $str .= '---------------------------' . \PHP_EOL;
        $str .= sprintf('TOTAL:   %6s   %6s b', \Tk\FileUtil::bytes2String($t), $t) . \PHP_EOL;
        $str .= '---------------------------';
        return $str;
    }

    public function __toString()
    {
        return $this->toString();
    }


}
