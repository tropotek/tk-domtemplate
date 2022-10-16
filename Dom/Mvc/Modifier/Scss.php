<?php
namespace Dom\Mvc\Modifier;


use Dom\Exception;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\ValueConverter;
use Tk\Cache\Adapter\Filesystem;
use Tk\Cache\Cache;

/**
 * Compile all CSS LESS code to CSS
 *
 * To Enable use composer.json to include LESS package.
 *
 * {
 *   "require": {
 *     "scssphp/scssphp": "1.0.*"
 *   }
 * }
 *
 * @see http://leafo.github.io/scssphp/docs/
 */
class Scss extends FilterInterface
{

    protected Cache $cache;

    protected int $cacheTimeout = 86400 * 2;  // 2 days

    protected bool $compress = true;

    protected array $source = [];

    protected array $sourcePaths = [];

    private ?\DOMElement $insNode = null;

    protected string $basePath = '';

    protected string $baseUrl = '';

    protected array $constants = [];

    protected bool $cacheEnabled = true;


    /**
     * @param array $constants Any parameters you want accessible via the less file via @{paramName}
     */
    public function __construct($basePath, $baseUrl, string $cachePath, array $constants = [])
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->constants = $constants;
        $this->cache = new Cache(new Filesystem($cachePath));
        if (!is_writable($cachePath)) {
            $this->cacheEnabled = false;
        }
    }

    /**
     * pre init the Filter
     *
     * @throws Exception
     */
    public function init(\DOMDocument $doc)
    {
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            throw new Exception('Please install composer package scssphp. (https://packagist.org/packages/scssphp/scssphp) [Composer: "scssphp/scssphp": "1.0.*"]');
        }
    }

    /**
     * Call this method to traverse a document
     */
    public function executeNode(\DOMElement $node)
    {
        if ($node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.scss/', $node->getAttribute('href'))) {
            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->basePath . $url->getRelativePath();
            $this->source[$path] = '';
            //$this->sourcePaths[] = $path;   // For adding to data-paths attributes
            $this->sourcePaths[] = $url->getRelativePath();
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/scss' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        }
    }

    /**
     * pre init the Filter
     *
     * @throws \Exception
     */
    public function postTraverse(\DOMDocument $doc)
    {
        $scss = new \ScssPhp\ScssPhp\Compiler();

        foreach ($this->constants as $k => $v) {
            $this->constants[$k] = ValueConverter::fromPhp($v);
        }
        $scss->addVariables($this->constants);

        $scss->setOutputStyle($this->isCompress() ? OutputStyle::COMPRESSED : OutputStyle::EXPANDED);

        $css = '';
        foreach ($this->source as $path => $v) {
            if (preg_match('/\.scss/', $path) && is_file($path)) {
                $cCss = '';
                $cacheKey = 'scss_' . hash('md5', $path);
                if ($this->cacheEnabled) {
                    $cCss = $this->cache->fetch($cacheKey);
                }
                if (!$cCss) {
                    \Tk\Log::notice('SCSS Compiling File: ' . $path);
                    $scss->setImportPaths(array($this->baseUrl, dirname($path)));
                    // TODO: Test if this path is a file or dir
                    $src = file_get_contents($path);
                    $cCss = $scss->compileString($src);
                    $this->cache->store($cacheKey, $cCss, $this->cacheTimeout);
                }
                $css .= $cCss->getCss();
            } else {
                \Tk\Log::warning('Invalid file: ' . $path);
            }
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            if ($this->isDebug()) {
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
     */
    protected function enquote(string $str, string $quote = '"'): string
    {
        return $quote . $str . $quote;
    }

    public function isCompress(): bool
    {
        return $this->compress;
    }

    public function setCompress(bool $compress): Scss
    {
        $this->compress = $compress;
        return $this;
    }

    public function setCacheEnabled(bool $cacheEnabled): Scss
    {
        $this->cacheEnabled = $cacheEnabled;
        return $this;
    }

    public function getCacheTimeout(): int
    {
        return $this->cacheTimeout;
    }

    public function setCacheTimeout(int $cacheTimeout): Scss
    {
        $this->cacheTimeout = $cacheTimeout;
        return $this;
    }

    public function isDebug(): bool
    {
        return (class_exists('\Tk\Config') && \Tk\Config::instance()->isDebug());
    }
}
