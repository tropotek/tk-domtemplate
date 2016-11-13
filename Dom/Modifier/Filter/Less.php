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
 *     "oyejorge/less.php": "~1.5"
 *   }
 * }
 *
 * @todo: need to implement this for the TK3 libs
 */
class Less extends Iface
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
    protected $lessConstants = array();


    /**
     * __construct
     * @param $sitePath
     * @param $siteUrl
     * @param string $cachePath
     * @param array $lessConstants Any parameters you want accessable via the less file via @{paramName}
     */
    public function __construct($sitePath, $siteUrl, $cachePath = '', $lessConstants = array())
    {
        $this->sitePath = $sitePath;
        $this->siteUrl = $siteUrl;
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
     * pre init the Filter
     *
     * @param \DOMDocument $doc
     * @throws Exception
     */
    public function init($doc)
    {
        if (!class_exists('Less_Parser')) {
            throw new Exception('Please install lessphp. (http://lessphp.gpeasy.com/) [Composer: "oyejorge/less.php": "~1.5"]');
        }

        $src = '';
        foreach ($this->lessConstants as $k => $v) {
            $src .= sprintf('@%s : %s;', $k, $this->enquote($v)) . "\n";
        }
        if ($src)
            $this->source[] = $src;
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
        //$css_file_name = \Less_Cache::Get($this->source, $options, false);
        if ($this->cachePath) {
            $options = array('cache_dir' => $this->cachePath, 'compress' => $this->compress);
            $css_file_name = \Less_Cache::Get($this->source, $options);
            $css = trim(file_get_contents($this->cachePath . '/' . $css_file_name));
        } else {
            // todo: Make the caching optional
            //$options = array('compress' => $this->compress);
            throw new \Exception('Non cached parser not implemented, please supply a cachePath value');
        }

        if ($css) {
            $newNode = $doc->createElement('style');
            $newNode->setAttribute('type', 'text/css');
            $newNode->setAttribute('data-author', 'PHP_LESS_Compiler');
            $newNode->setAttribute('data-paths', implode(',', $this->sourcePaths));
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
     * @throws Exception
     */
    public function executeNode(\DOMElement $node)
    {
        if ($node->nodeName == 'link' && $node->hasAttribute('href') && preg_match('/\.less$/', $node->getAttribute('href'))) {
            $url = \Tk\Uri::create($node->getAttribute('href'));
            $path = $this->sitePath . $url->getRelativePath();

            $this->source[$path] = '';
            $this->sourcePaths[] = $path;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        } else if ($node->nodeName == 'style' && $node->getAttribute('type') == 'text/less' ) {
            $this->source[] = $node->nodeValue;
            $this->domModifier->removeNode($node);
            $this->insNode = $node;
        }
    }

}
