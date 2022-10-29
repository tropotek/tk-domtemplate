<?php
namespace Dom\Renderer\Util;

use Dom\Renderer\Renderer;
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
 * @note development on this object as it does not belong with this
 * library. It introduces the need for logic into the template which is what we are trying to avoid.
 * It is left here as a reference so if you wish to build on the base Template system
 * this shows how you could create an automated template renderer by sending array of params
 * and build your own template logic renderer
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class AutoRenderer extends Renderer
{
    private \stdClass $data;


    public function __construct(Template $template = null, array|\stdClass $data = null)
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
     */
    public function exists(string $name): bool
    {
        return property_exists($this->data, $name);
    }

    /**
     * Add an item to the renderer data list
     */
    public function set(string $name, string $val)
    {
        $this->data->$name = $val;
    }

    /**
     * Get an element from the renderer data list
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (!isset($this->data->$name)) return $default;
        return $this->data->$name;
    }

    public function all(): \stdClass
    {
        return $this->data;
    }


    /**
     * Execute the renderer.
     * This method can optionally return a Template Object
     * or HTML/XML string depending on your framework requirements
     */
    public function show(): ?Template
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
     */
    protected function showRepeat(Template $template, ?string $varVal = null): static
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
     */
    protected function showChoice(Template $template, ?string $varVal = null): static
    {
        $vars = array_keys($template->getChoiceList());
        foreach ($vars as $paramStr) {
            $val = $this->getParameter($paramStr, $varVal);
            if ($val != null) {
                $template->setVisible($paramStr);
            }
        }
        return $this;
    }

    /**
     * Render all vars found in the template
     */
    protected function showVars(Template $template, ?string $varVal = null): static
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
     */
    protected function getParameter(string $rawParam, ?string $varVal = null): mixed
    {
        $arr = explode('.', $rawParam);
        if ($varVal === null) {
            $varVal = (object)$this->data;
        }
        foreach ($arr as $varPeice) {
            if (!str_contains($varPeice, '[')) {
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
     */
    protected function accessArray(array $array, string $keys): mixed
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
     */
    protected function varToStr(mixed $val, int $i = 0): string
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
     * Parse the template with the supplied params
     */
    public function toString(array $params = []): string
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

    public function __toString()
    {
        return $this->toString();
    }
}