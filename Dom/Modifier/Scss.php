<?php
namespace Dom\Modifier;

use Dom\Exception;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\ValueConverter;
use Tk\Cache\FileCache;
use Tk\Config;
use Tk\Log;
use Tk\System;
use Tk\Uri;

/**
 * Compile any SCSS code to CSS found within a template.
 *
 * This enables the ability to add SCSS files to HTML template headers just like CSS:
 * ```
 *    <link href="https://example.org/assets/css/bootstrap.scss" rel="stylesheet">
 * ```
 *
 * They will be compiled/cached, the CSS result will be added in it place.
 * All files are compiled together, see http://leafo.github.io/scssphp/docs/ for more info.
 *
 * To Enable use composer.json to include LESS package.
 *
 * {
 *   "require": {
 *     "scssphp/scssphp": "^1.11.0-@stable"
 *   }
 * }
 *
 * @see http://leafo.github.io/scssphp/docs/
 * @requires https://github.com/tropotek/tk-framework (v8.0+)
 */
class Scss extends ModifierInterface
{
    private ?\DOMElement $insNode = null;

    protected int       $cacheTimeout = 86400 * 7;  // 7 days
    protected bool      $compress     = true;
    protected array     $source       = [];
    protected array     $sourcePaths  = [];
    protected array     $constants    = [];
    protected bool      $perPageCache = false;

    protected ?string   $cachePath    = null;       // null cachePath = cache disabled
    protected string    $basePath     = '';
    protected string    $baseUrl      = '';
    protected bool      $enabled      = true;


    /**
     * @param array $constants Any parameters you want accessible via the scss parser via @{paramName}
     */
    public function __construct(array $constants = [], ?string $cachePath = null, ?string $basePath = null, ?string $baseUrl = null)
    {
        $this->constants    = $constants;
        $this->cachePath    = $cachePath;
        $this->basePath     = is_null($basePath) ? Config::getBasePath() : $basePath;
        $this->baseUrl      = is_null($baseUrl) ? Config::getBaseUrl() : $baseUrl;
        $this->enabled      = class_exists('ScssPhp\ScssPhp\Compiler');

        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            $this->enabled = false;
            Log::warning('ScssPhp is not enabled. Please install scssphp/scssphp composer package. [Installer: "scssphp/scssphp": "^1.11.0-@stable"]');
        }
    }

    public function init(\DOMDocument $doc): void
    {

    }

    public function executeNode(\DOMElement $node): void
    {
        if (!$this->enabled) return;

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
        if (!$this->enabled) return;

        $scss = new \ScssPhp\ScssPhp\Compiler();
        $cache = new FileCache($this->cachePath, $this->cacheTimeout);

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

        $css = $cache->fetch($cacheKey);

        if (($css === false) || System::isRefreshCacheRequest()) {
            foreach ($this->source as $path => $v) {
                if (preg_match('/\.scss/', $path) && is_file($path)) {
                    //\Tk\Log::debug('SCSS Compiling File: ' . $path);
                    $scss->setImportPaths(array($this->baseUrl, dirname($path)));
                    $src = strval(file_get_contents($path));
                    $cCss = $scss->compileString($src);
                    $css .= $cCss->getCss();
                } else {
                    \Tk\Log::warning('Invalid SCSS file: ' . $path);
                }
            }
            if (!empty($css)) {
                $cache->store($cacheKey, $css);
            }
        }

        if (!empty($css)) {
            $newNode = $doc->createElement('link');
            $cssUrl = Uri::createDataUri('/cache/' . $cacheKey, ['t' => filemtime($cache->getFileName($cacheKey))]);
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
