<?php
namespace Dom\Util;

use Dom\Exception;

/**
 * Class XmlObj
 *
 * @author Tropotek <http://www.tropotek.com/>
 */
class XmlObj
{

    /**
     * Convert an XML string to a stdObj
     * We use this instead of simpleXML because it returns native strings.
     */
    public static function xml2Obj(string $xml): ?\stdClass
    {
        if ($xml[0] != '<') {
            $xml = file_get_contents($xml);
            if ($xml === false) {
                throw new \Exception("cannot load file");
            }
        }
        $dom = new \DOMDocument();
        $r = $dom->loadXML($xml);
        if (!$r) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            throw new Exception('Invalid XML cannot convert To DOM Object.', 0, null, $str);
        }

        return self::dom2Obj($dom->documentElement);
    }

    /**
     * Convert a dom node and its children to a stdClass object
     */
    public static function dom2Obj(\DOMNode $node): ?\stdClass
    {
        $node->normalize();
        if ($node->firstChild != null) {
            if ($node->childNodes->length == 1 && $node->firstChild->nodeType == \XML_TEXT_NODE) {
                //return (object)[$node->firstChild->nodeName] = trim($node->firstChild->nodeValue);
                return (object)[$node->firstChild->nodeName => trim($node->firstChild->nodeValue)];
            }
        } else {
            return null;
        }
        $obj = new \stdClass();
        $children = $node->childNodes;
        foreach ($children as $child) {
            if ($child->nodeType == \XML_ELEMENT_NODE) {
                $property = $child->nodeName;
                $value = self::dom2Obj($child);
                if (isset($obj->$property)) {
                    if (!is_array($obj->$property)) {
                        $tmp = $obj->$property;
                        $obj->$property = array();
                        $obj->{$property}[] = $tmp;
                    }
                    $obj->{$property}[] = $value;
                } else {
                    $obj->$property = $value;
                }
            }
        }
        return $obj;
    }

}