<?php
namespace Dom\Renderer;

use Dom\Template;
use Dom\Exception;

/**
 * For classes that render dom templates.
 *
 * This is a good base for all renderer objects that implement the \Dom_Template
 * it can guide you to create templates that can be inserted into other template
 * objects.
 *
 * If the current template is null then
 * the magic method __makeTemplate() will be called to create an internal template.
 * This is a good way to create a default template. But be aware that this will
 * be a new template and will have to be inserted into its parent using the \Dom_Template::insertTemplate()
 * method.
 *
 *
 *
 * @note I have ceased development on this object as I feel it does not belong with this
 * library and introduces the need for logic into the template which is what we are trying to avoid.
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2007 Michael Mifsud
 * @deprecated Use this as a template to create something to suit your own requirements...
 */
class AutoRenderer implements RendererInterface, DisplayInterface
{

    /**
     * @var Template
     */
    protected $template = null;

    /**
     * @var \stdClass
     */
    private $data = null;


    /**
     * Constructor
     *
     * @param array $data
     * @param Template $template
     */
    public function __construct($template = null, $data = null)
    {
        if ($template) {
            $this->template = $template;
        }
        if (!$data) {
            $data = new \stdClass();
        }
        $this->data = (object)$data;
    }

    /**
     * Test if an array key exists in the renderer data list
     *
     * @param $name
     * @return bool
     */
    public function exists($name)
    {
        return property_exists($this->data, $name);
    }

    /**
     * Add an item to the renderer data list
     *
     * @param string $name
     * @param mixed $val
     */
    public function set($name, $val)
    {
        $this->data->$name = $val;
    }

    /**
     * Get an element from the renderer data list
     *
     * @param string $name If not set then all the data array is returned
     * @return mixed
     */
    public function get($name = null)
    {
        return $this->data->$name;
    }


    /**
     * Execute the renderer.
     * This method can optionally return a Template Object
     * or HTML/XML string depending on your framework requirements
     *
     * @return Template | string
     * @throws Exception
     */
    public function show()
    {
        $template = $this->getTemplate();
        // VAR
        $this->showVars($template);
        // CHOICE
        $this->showChoice($template);
        // REPEAT
        $this->showRepeat($template);

        return $template;
    }


    /**
     * Render all vars found in the template
     *
     * @param Template $template
     * @param string|null $varVal
     * @return $this
     * @throws Exception
     */
    protected function showRepeat($template, $varVal = null)
    {
        $vars = array_keys($template->getRepeatList());
        foreach ($vars as $i => $paramStr) {
            $val = $this->getParameter($paramStr, $varVal);
            if (is_array($val)) {
                foreach ($val as $obj) {
                    $rpt = $template->getRepeat($paramStr);
                    $this->showVars($rpt, $obj);
                    $this->showRepeat($rpt, $obj);
                    $rpt->appendRepeat();
                }
            }
        }
        return $this;
    }

    /**
     * Render all vars found in the template
     *
     * @param Template $template
     * @param string|null $varVal
     * @return $this
     */
    protected function showChoice($template, $varVal = null)
    {
        $vars = array_keys($template->getChoiceList());
        foreach ($vars as $paramStr) {
            $val = $this->getParameter($paramStr, $varVal);
            if ($val != null) {
                $template->setChoice($paramStr);
            }
        }
        return $this;
    }

    /**
     * Render all vars found in the template
     *
     * @param Template    $template
     * @param string|null $varVal
     * @return $this
     * @throws Exception
     */
    protected function showVars($template, $varVal = null)
    {
        $vars = array_keys($template->getVarList());
        foreach ($vars as $paramStr) {
            $arr = explode(':', $paramStr);
            $modifier = '';
            $rawParam = $arr[0];
            if (count($arr) > 1) {
                $modifier = $arr[0];
                $rawParam = $arr[1];
            }
            $paramValue = $this->varToStr($this->getParameter($rawParam, $varVal));
            // TODO: Add the ability to extend/add modifiers....
            $arr = explode('.', $modifier);
            if (count($arr) > 1) {
                $modifier = $arr[0];
            }
            switch (strtolower($modifier)) {
                case 'attr':
                    if (count($arr) != 2) {
                        throw new Exception('Error: Attribute modifier must look like `attr.href:...`');
                    }
                    $template->setAttr($paramStr, $arr[1], $paramValue);
                    break;
                case 'ucfirst':
                    $template->appendText($paramStr, ucfirst($paramValue));
                    break;
                case 'html':
                    $template->appendHtml($paramStr, $paramValue);
                    break;
                case 'append':
                default:
                    $template->appendText($paramStr, $paramValue);
            }
        }
        return $this;
    }

    /**
     * getParameter
     *
     * @param string $rawParam
     * @param string|null $varVal
     * @return mixed
     */
    protected function getParameter($rawParam, $varVal = null)
    {
        $arr = explode('.', $rawParam);
        if ($varVal === null) {
            $varVal = (object)$this->data;
        }
        foreach ($arr as $varPeice) {
            if (strpos($varPeice, '[') === false) {
                if (!isset($varVal->$varPeice)) {
                    $varVal = null;
                    break;
                }
                $varVal = $varVal->$varPeice;
            } else {
                $arrayName = substr($varPeice, 0, strpos($varPeice, '['));
                if ($arrayName && (!isset($varVal->$arrayName) || !is_array($varVal->$arrayName))) {
                    $varVal = null;
                    break;
                }
                if (!$arrayName && $varVal) {
                    $varVal = $this->accessArray($varVal, substr($varPeice, strpos($varPeice, '[')));
                } else {
                    $varVal = $this->accessArray($varVal->$arrayName, substr($varPeice, strpos($varPeice, '[')));
                }
            }
        }
        return $varVal;
    }

    /**
     * accessArray
     *
     * @param array $array
     * @param string $keys Example "['test'][0]['item']"
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function accessArray($array, $keys)
    {
        if (!preg_match_all('~\[([^\]]+)\]~', $keys, $matches)) {
            throw new \InvalidArgumentException();
        }
        $keys1 = $matches[1];
        $current = $array;
        foreach ($keys1 as $key) {
            $key = str_replace(array("'", '"'), '', $key);
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * Get the string from an object/array/string...
     *
     * @param mixed $val
     * @param int $i Used to get the index from an array Default 0 the first item
     * @return string
     */
    protected function varToStr($val, $i = 0)
    {
        if (is_object($val)) {
            if (method_exists($val, 'toString')) {
                return $val->toString();
            } else {
                return $val->__toString();
            }
        }
        if (is_array($val)) {
            return $this->varToStr($val[$i]);
        }
        if ($val === false || $val === null) {
            return '';
        }
        if ($val === true) {
            return 'true';
        }
        return (string)$val;
    }


    /**
     * Set a new template for this renderer.
     *
     * @param Template $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Get the template
     * This method will try to call the magic method __makeTemplate
     * to get a template if none exits.
     * Use this for objects that use internal templates.
     *
     * @return Template
     */
    public function getTemplate()
    {
        if ($this->hasTemplate()) {
            return $this->template;
        }
        $magic = '__makeTemplate';
        if (!$this->hasTemplate() && method_exists($this, $magic)) {
            $this->template = $this->$magic();
        }
        return $this->template;
    }

    /**
     * Test if this renderer has a template and is not NULL
     *
     * @return bool
     */
    public function hasTemplate()
    {
        if ($this->template) {
            return true;
        }
        return false;
    }


    /**
     * Parse the template with the supplied params
     *
     * @param array $params
     * @return string
     */
    public function toString($params = null)
    {
        $str = "";
        $ext = true;
        if (!$params) {
            $ext = false;
            $params = (array)$this->data;
        }

        ksort($params);
        foreach ($params as $k => $v) {
            if (is_object($v)) {
                $str .= "[$k] => {" . get_class($v) . "}\n";
            } elseif (is_array($v)) {
                $str .= "[$k] =>  array[" . count($v) . "]\n";
            } else {
                $str .= "[$k] => $v\n";
            }
        }

        if (!$ext && $this->hasTemplate()) {
            $str .= "Template: \n" . $this->getTemplate()->toString(false) . "\n\n";
        }
        return $str;
    }

    /**
     * __toString
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}