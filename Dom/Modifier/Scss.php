<?php
namespace Dom\Modifier;

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
class Scss extends ModifierInterface
{
    public static bool $IS_DEBUG = false;

    private ?\DOMElement $insNode = null;

    protected int    $cacheTimeout = 86400 * 2;  // 2 days
    protected bool   $compress     = true;
    protected array  $source       = [];
    protected array  $sourcePaths  = [];
    protected string $basePath     = '';
    protected string $baseUrl      = '';
    protected array  $constants    = [];
    protected bool   $cacheEnabled = true;
    protected Cache  $cache;


    /**
     * @param array $constants Any parameters you want accessible via the scss parser via @{paramName}
     */
    public function __construct(string $basePath, string $baseUrl, array $constants = [])
    {
        $this->basePath     = $basePath;
        $this->baseUrl      = $baseUrl;
        $this->constants    = $constants;
        $this->cache        = Cache::instance();
        $this->cacheEnabled = false;
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
            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->basePath . $url->getRelativePath();
            $this->source[$path] = '';
            $this->sourcePaths[] = $url->getRelativePath();
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/scss' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
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

        $css = '';
        foreach ($this->source as $path => $v) {
            if (preg_match('/\.scss/', $path) && is_file($path)) {
                $cCss = '';
                $cacheKey = 'scss_' . hash('md5', $path);
                if ($this->isCacheEnabled()) {
                    $cCss = $this->cache->fetch($cacheKey);
                }
                if (!$cCss) {
                    \Tk\Log::notice('SCSS Compiling File: ' . $path);
                    $scss->setImportPaths(array($this->baseUrl, dirname($path)));
                    $src = strval(file_get_contents($path));
                    $cCss = $scss->compileString($src);
                    $this->cache->store($cacheKey, $cCss, $this->cacheTimeout);
                }
                $css .= $cCss->getCss();
            } else {
                \Tk\Log::notice('Invalid file: ' . $path);
            }
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            if (self::$IS_DEBUG) {
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
}
