<?php
namespace Dom\Modifier\Filter;

use Dom\Modifier\Exception;
use Tk\Log;

/**
 * Compile all CSS LESS code to CSS
 *
 * To Enable use composer.json to include LESS package.
 *
 * {
 *   "require": {
 *     "wikimedia/less.php": "~1.5"
 *   }
 * }
 *
 * @todo: need to implement this for the TK3 libs
 */
class Less extends Iface
{

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
    protected $cacheEnabled = true;

    /**
     * @var array
     */
    protected $lessConstants = array();


    /**
     * __construct
     * @param $sitePath
     * @param $siteUrl
     * @param string $cachePath
     * @param array $lessConstants Any parameters you want accessable via the less file via @{paramName}
     */
    public function __construct($sitePath, $siteUrl, $cachePath, $lessConstants = array())
    {
        $this->sitePath = $sitePath;
        $this->siteUrl = $siteUrl;
        if (!is_writable($cachePath)) {
            $this->cacheEnabled = false;
            \Tk\Log::warning('Cannot write to cache path: ' . $cachePath);
        }
        $this->cachePath = $cachePath;
        $this->lessConstants = $lessConstants;
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
     * @param bool $cacheEnabled
     * @return $this
     */
    public function setCacheEnabled(bool $cacheEnabled)
    {
        $this->cacheEnabled = $cacheEnabled;
        return $this;
    }

    /**
     * pre init the Filter
     *
     * @param \DOMDocument $doc
     */
    public function init($doc)
    {
        if (!class_exists('Less_Parser')) {
            \Tk\Log::warning('Please install lessphp. (https://github.com/wikimedia/less.php) [Composer: "wikimedia/less.php": "1.7.*"]');
        }

        $src = '';
        foreach ($this->lessConstants as $k => $v) {
            $src .= sprintf('@%s : %s;', $k, $this->enquote($v)) . "\n";
        }
        if ($src)
            $this->source[] = $src;
    }

    /**
     * A callback method for the LESS compiler
     * get path & uri
     * 
     * @param \Less_Tree_Import $import
     * @return array()  EG: array('/file/path.less', '/~file/uri.less')
     */
    public function doImport($import)
    {
        // Allow including of /vendor/... less files using: @import '/vendor/package/lib/less/lessfile.less'
        if (!preg_match('/^\/vendor\//',$import->getPath())) return array();
        $path = $import->getPath();
        if (!preg_match('/\.less$/',$path)) {
            $path = $path.'.less';
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
        $css = '';
        if ($this->cacheEnabled) {
            foreach (array_keys($this->source) as $path) {
                if (preg_match('/\.less$/', $path) && !is_file($path)) {
                    \Tk\Log::warning('Invalid LESS file: ' . $path);
                }
            }
            // TODO: Cache bug for inline styles, the compiled_file hash does not include them, this can cause inline styles to remain
            // TODO: Regen() the css files seems to fix this, this may only be a real issue in Debug mode.
            $css_file_name = \Less_Cache::Get($this->source, $options);

            $path = $this->cachePath . '/' . $css_file_name;
            if (is_file($path))
                $css = trim(file_get_contents($path));
        } else {
            $css_file_name = \Less_Cache::Regen($this->source, $options);
            if (is_file($this->cachePath . '/' . $css_file_name))
                $css = trim(file_get_contents($this->cachePath . '/' . $css_file_name));
            else
                Log::error('Path is a directory: ' . $this->cachePath . '/' . $css_file_name );
            //throw new \Exception('LESS Parser: Non cached parser not implemented, please supply a valid `cachePath` value');
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            //$newNode->setAttribute('data-author', 'lessphp_compiler');
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
        if ($node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.less$/', $node->getAttribute('href'))) {
            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->sitePath . $url->getRelativePath();
            $this->source[$path] = '';
            //$this->sourcePaths[] = $path;   // For adding to data-paths attruibute
            $this->sourcePaths[] = $url->getRelativePath();
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/less' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        }
    }

}
