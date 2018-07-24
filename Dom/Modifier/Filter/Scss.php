<?php
namespace Dom\Modifier\Filter;

use Dom\Modifier\Exception;

/**
 * Compile all CSS LESS code to CSS
 *
 * To Enable use composer.json to include LESS package.
 *
 * {
 *   "require": {
 *     "leafo/scssphp": "~0.7.7"
 *   }
 * }
 *
 * @see http://leafo.github.io/scssphp/docs/
 */
class Scss extends Iface
{
    /**
     * @var \Tk\Cache\Cache
     */
    protected $cache = null;

    /**
     * The number of hours to refresh the cache.
     * @var int
     */
    protected $hours = 6;

    /**
     * @var bool
     */
    protected $compress = true;

    /**
     * @var array
     */
    protected $source = array();

    /**
     * @var array
     */
    protected $sourcePaths = array();

    /**
     * @var null|\DOMElement
     */
    private $insNode = null;


    /**
     * @var string
     */
    protected $sitePath = '';

    /**
     * @var string
     */
    protected $siteUrl = '';

    /**
     * @var string
     */
    protected $cachePath = '';

    /**
     * @var array
     */
    protected $constants = array();


    /**
     * __construct
     * @param $sitePath
     * @param $siteUrl
     * @param string $cachePath
     * @param array $constants Any parameters you want accessible via the less file via @{paramName}
     */
    public function __construct($sitePath, $siteUrl, $cachePath = '', $constants = array())
    {
        $this->sitePath = $sitePath;
        $this->siteUrl = $siteUrl;
        $this->cachePath = $cachePath;
        if (!is_writable($cachePath)) {
            \Tk\Log::warning('Cannot write to cache path: ' . $cachePath);
        }
        $this->constants = $constants;
        $this->cache = new \Tk\Cache\Cache(new \Tk\Cache\Adapter\Filesystem($cachePath));
    }

    /**
     * @param $b
     * @return $this
     */
    public function enableCache($b)
    {
        $this->useCache = $b;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isCompress()
    {
        return $this->compress;
    }

    /**
     * @param boolean $compress
     * @return $this
     */
    public function setCompress($compress)
    {
        $this->compress = $compress;
        return $this;
    }

    /**
     * pre init the Filter
     *
     * @param \DOMDocument $doc
     */
    public function init($doc)
    {
        if (!class_exists('Leafo\ScssPhp\Compiler')) {
            \Tk\Log::warning('Please install scssphp. (http://leafo.github.io/scssphp/) [Composer: "leafo/scssphp": "~0.7.7"]');
        }
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
        $scss = new \Leafo\ScssPhp\Compiler();
        $scss->setVariables($this->constants);
        $scss->setFormatter(new \Leafo\ScssPhp\Formatter\Expanded());
        if ($this->isCompress()) {
            $scss->setFormatter(new \Leafo\ScssPhp\Formatter\Crunched());
        }

        $css = '';
        foreach ($this->source as $path => $v) {
            if (preg_match('/\.scss/', $path) && is_file($path)) {
                $cCss = $this->cache->fetch($path);
                if (!$cCss) {
                    $scss->setImportPaths(array($this->siteUrl, dirname($path)));
                    $src = file_get_contents($path);
                    $cCss = $scss->compile($src);
                    // Storing the data in the cache for 10 minutes
                    $this->cache->store($path, $cCss, $this->hours);
                }
                $css .= $cCss;
            } else {
                \Tk\Log::warning('Invalid file: ' . $path);
            }
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            //$newNode->setAttribute('data-author', 'scssphp_compiler');
            if (class_exists('\Tk\Config') && \Tk\Config::getInstance()->isDebug()) {
                $newNode->setAttribute('data-paths', implode(',', $this->sourcePaths));
            }
            $ct = $doc->createCDATASection("\n" . $css . "\n");
            $newNode->appendChild($ct);

            if ($this->insNode) {
                $this->insNode->parentNode->insertBefore($newNode, $this->insNode);
            } else {
                $this->domModifier->getHead()->appendChild($newNode);
            }
        }

    }

    /**
     * Surround a string by quotation marks. Single quote by default
     *
     * @param string $str
     * @param string $quote
     * @return string
     */
    protected function enquote($str, $quote = '"')
    {
        return $quote . $str . $quote;
    }

    /**
     * Call this method to traverse a document
     *
     * @param \DOMElement $node
     */
    public function executeNode(\DOMElement $node)
    {
        if ($node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.scss/', $node->getAttribute('href'))) {
            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->sitePath . $url->getRelativePath();
            $this->source[$path] = '';
            $this->sourcePaths[] = $path;   // For adding to data-paths attributes
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/scss' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        }
    }

}
