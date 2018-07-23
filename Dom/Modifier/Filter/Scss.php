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
 * @todo: need to implement this for the TK3 libs
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
     * @var boolean
     */
    protected $useCache = true;

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
            //throw new \Tk\Exception('Cannot write to cache path: ' . $cachePath);
        }
        $this->constants = $constants;
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

        $src = '';
        foreach ($this->constants as $k => $v) {
            $src .= sprintf('@%s : %s;', $k, $this->enquote($v)) . "\n";
        }
        if ($src)
            $this->source[] = $src;
    }

    /**
     * get path & uri
     * 
     * @param \Less_Tree_Import $import
     * @return array()  EG: array('/file/path.scss', '/~file/uri.scss')
     */
    public function doImport($import)
    {
        // Allow including of /vendor/... less files using: @import '/vendor/package/lib/scss/scssfile.scss'
        if (!preg_match('/^\/vendor\//',$import->getPath())) return array();

        $path = $import->getPath();
        if (!preg_match('/\.scss/',$path)) {
            $path = $path.'.scss';
        }

        return array($this->sitePath.$path, $this->siteUrl.$path);
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
        $options = array('cache_dir' => $this->cachePath, 'compress' => $this->compress, 'import_dirs' => array($this->siteUrl),
            'import_callback' => array($this, 'doImport'));

        if ($this->cachePath) {
            foreach (array_keys($this->source) as $path) {
                if (preg_match('/\.scss/', $path) && !is_file($path)) {
                    \Tk\Log::warning('Invalid file: ' . $path);
                }
            }
            // TODO: Cache bug for inline styles, the compiled_file hash does not include them, this can cause inline styles to remain
            // TODO: Regen() the css files seems to fix this, this may only be a real issue in Debug mode.
            $css_file_name = \Less_Cache::Get($this->source, $options);
            $css = trim(file_get_contents($this->cachePath . '/' . $css_file_name));
        } else {
            \Less_Cache::Regen($this->source, $options);
            throw new \Exception('Parser: Non cached parser not implemented, please supply a valid `cachePath` value');
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            //$newNode->setAttribute('data-author', 'PHP_LESS_Compiler');
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
            $this->sourcePaths[] = $path;   // For adding to data-paths attruibute
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/scss' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        }
    }

}
