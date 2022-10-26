<?php
namespace Dom\Renderer\Traits;

/**
 * Use this class to manage css class attributes for an element
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
trait CssTrait
{

    protected array $_cssList = [];


    /**
     * Clean CSS class replacing non-alphanumeric chars with '-'
     */
    public static function cleanCss(string $css): string
    {
        return preg_replace("/\W|_/", "-", $css);
    }

    /**
     * Does the css class exist
     */
    public function hasCss(string $css): bool
    {
        return array_key_exists(self::cleanCss($css), $this->_cssList);
    }

    /**
     * Add a css class
     */
    public function addCss(string $css): static
    {
        foreach (explode(' ', $css) as $c) {
            if (!$c) continue;
            $c = self::cleanCss($c);
            $this->_cssList[$c] = $c;
        }
        return $this;
    }

    /**
     * remove a css class
     */
    public function removeCss(string $css): static
    {
        foreach (explode(' ', $css) as $c) {
            if (!$c) continue;
            $c = self::cleanCss($c);
            unset($this->_cssList[$c]);
        }
        return $this;
    }

    /**
     * Get the css class list
     */
    public function getCssList(): array
    {
        return $this->_cssList;
    }

    /**
     * Set the css cell class list
     * If no parameter sent the array is cleared.
     */
    public function setCssList(array $arr = []): static
    {
        $this->_cssList = $arr;
        return $this;
    }

    /**
     * return the css string in the form of a css class list
     * Eg:
     *   'class-one class-two class-three'
     */
    public function getCssString(): string
    {
        return trim(implode(' ', $this->_cssList));
    }

}