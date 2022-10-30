<?php
namespace Dom\Renderer\Traits;

/**
 * This Trait can be used with object representing a DOM Element node
 * so that attributes can be managed
 *
 * $attrList Source:
 *   array('style' => 'color: #000;', 'id' => 'test-id');
 *
 * Rendered Result:
 *   <div id="test-id" style="color: #000;"></div>
 *
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
trait AttributesTrait
{

    protected array $_attrList = [];


    /**
     * Set an attribute
     */
    public function setAttr(array|string $name, string $value = null): static
    {
        if (is_array($name)) {
            $this->_attrList = $this->_attrList + $name;
        } else {
            $name = strip_tags(trim($name));
            $this->_attrList[$name] = $value ?? $name;
        }
        return $this;
    }

    /**
     * Does the attribute exist
     */
    public function hasAttr(string $name): bool
    {
        return array_key_exists($name, $this->_attrList);
    }

    /**
     * Get an attributes value string
     */
    public function getAttr(string $name, string $default = ''): string
    {
        return $this->_attrList[$name] ?? $default;
    }

    /**
     * remove an attribute
     */
    public function removeAttr(string $name): static
    {
        if ($this->hasAttr($name)) {
            unset($this->_attrList[$name]);
        }
        return $this;
    }

    /**
     * Get the attributes list
     */
    public function getAttrList(): array
    {
        return $this->_attrList;
    }

    /**
     * Set the attributes list
     * If no parameter sent the array is cleared.
     */
    public function setAttrList(array $arr): static
    {
        $this->_attrList = $arr;
        return $this;
    }

    /**
     * Return an attribute string that can be inserted into HTML
     *
     * Eg:
     *   'id="test-id" style="color: #000;" data-attr="test"'
     */
    public function getAttrString(): string
    {
        $str = '';
        foreach ($this->_attrList as $k => $v) {
            $str = sprintf('%s="%s" ', $k, $v);
        }
        return trim($str);
    }

}