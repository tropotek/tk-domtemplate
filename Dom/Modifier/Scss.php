<?php
namespace Dom\Modifier;

use Dom\Exception;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\ValueConverter;
use Tk\Cache\Adapter\Filesystem;
use Tk\Cache\Cache;
use Tk\Cache\FileCache;
use Tk\Config;
use Tk\Path;
use Tk\System;
use Tk\Uri;

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
class Scss extends ModifierInterface
{
    public static bool $IS_DEBUG = false;

    private ?\DOMElement $insNode = null;

    protected int    $cacheTimeout = 86400 * 7;  // 7 days
    protected bool   $compress     = true;
    protected array  $source       = [];
    protected array  $sourcePaths  = [];
    protected string $basePath     = '';
    protected string $baseUrl      = '';
    protected array  $constants    = [];
    protected bool   $cacheEnabled = true;
    protected bool   $perPageCache = false; // create a cache per page
    protected FileCache $cache;


    /**
     * @param array $constants Any parameters you want accessible via the scss parser via @{paramName}
     */
    public function __construct(string $basePath, string $baseUrl, array $constants = [])
    {
        $this->basePath     = $basePath;
        $this->baseUrl      = $baseUrl;
        $this->constants    = $constants;
        $this->cache        = new FileCache(Path::createDataPath('/cache'), $this->cacheTimeout);
        //$this->cacheEnabled = false;
    }

    public function init(\DOMDocument $doc): void
    {
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            throw new Exception('Please install composer package scssphp. (https://packagist.org/packages/scssphp/scssphp) [Installer: "scssphp/scssphp": "1.0.*"]');
        }
    }

    public function executeNode(\DOMElement $node): void
    {
        if ($node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.scss/', $node->getAttribute('href'))) {
            if (!$this->insNode) {
                $this->insNode = $node->previousElementSibling;
            }

            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->basePath . $url->getRelativePath();
            $this->source[$path] = '';
            $this->sourcePaths[] = $url->getRelativePath();
            $this->domModifier->removeNode($node);
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/scss' ) {
            if (!$this->insNode) {
                $this->insNode = $node->previousElementSibling;
            }

            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
        }
    }

    public function postTraverse(\DOMDocument $doc): void
    {
        $scss = new \ScssPhp\ScssPhp\Compiler();

        foreach ($this->constants as $k => $v) {
            $this->constants[$k] = ValueConverter::fromPhp($v);
        }
        $scss->addVariables($this->constants);
        $scss->setOutputStyle($this->isCompress() ? OutputStyle::COMPRESSED : OutputStyle::EXPANDED);

        if ($this->isPerPageCache()) {
            $cacheKey = 'css_' . hash('md5', Uri::create()->getRelativePath()).'.css';
        } else {
            $cacheKey = 'css_cache.css';
        }
        $css = '';
        if ($this->isCacheEnabled()) {
            $css = $this->cache->fetch($cacheKey);
        }
        if (($css === false) || System::isRefreshCacheRequest()) {
            foreach ($this->source as $path => $v) {
                if (preg_match('/\.scss/', $path) && is_file($path)) {
                    \Tk\Log::debug('SCSS Compiling File: ' . $path);
                    $scss->setImportPaths(array($this->baseUrl, dirname($path)));
                    $src = strval(file_get_contents($path));
                    $cCss = $scss->compileString($src);
                    $css .= $cCss->getCss();
                } else {
                    \Tk\Log::warning('Invalid SCSS file: ' . $path);
                }
            }
            if (!empty($css)) {
                $this->cache->store($cacheKey, $css);
            }
        }

        if (!empty($css)) {
            $newNode = $doc->createElement('link');
            $cssUrl = Uri::create(Uri::createDataUri('/cache/' . $cacheKey));
            $newNode->setAttribute('href', $cssUrl->toString());
            $newNode->setAttribute('rel', 'stylesheet');
            $newNode->setAttribute('type', 'text/css');

            if ($this->insNode) {
                $this->insNode->parentElement->insertBefore($newNode, $this->insNode->nextElementSibling ?? $this->insNode);
            } elseif ($this->domModifier->getHead()) {
                $this->domModifier->getHead()->appendChild($newNode);
            }
        }

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

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
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

    public function isPerPageCache(): bool
    {
        return $this->perPageCache;
    }

    /**
     * Set this to true when you want to create a css cache for individual pages
     * Individual page caching should be used when each page has unique scss scripts
     */
    public function setPerPageCache(bool $perPageCache): Scss
    {
        $this->perPageCache = $perPageCache;
        return $this;
    }
}
