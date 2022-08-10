<?php
namespace Dom\Util;

use Dom\Exception;

/**
 * Class XmlObj
 *
 * @author Michael Mifsud <http://www.tropotek.com/>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class XmlObj
{
    
    /**
     * Convert an XML string to a stdObj
     * We use this instead of simpleXML because it returns native strings.
     *
     * @param string $xml
     * @return \stdClass
     * @throws Exception
     */
    static function xml2Obj($xml)
    {
        if ($xml[0] != '<') {
            $xml = file_get_contents($xml);
        }
        $dom = new \DOMDocument();
        $r = $dom->loadXML($xml);

//        if (!$dom->loadXML($xml)) {
//            $e = new Exception('Invalid XML cannot convert XML string to DOM.');
//            throw $e;
//        }
        if (!$r) {
            $str = '';
            foreach (libxml_get_errors() as $error) {
                $str .= sprintf("\n[%s:%s] %s", $error->line, $error->column, trim($error->message));
            }
            libxml_clear_errors();
            $e = new Exception('Invalid XML cannot convert To DOM Object.', 0, null, $str);
            //$e->setDump($str);
            throw $e;
        }

        $obj = self::dom2Obj($dom->documentElement);
        return $obj;
    }


    /**
     * Convert a dom node and its children to a stdClass object
     *
     * @param \DOMNode $node
     * @return \stdClass
     */
    public static function dom2Obj(\DOMNode $node)
    {
        $node->normalize();
        if ($node->firstChild != null) {
            if ($node->childNodes->length == 1 && $node->firstChild->nodeType == \XML_TEXT_NODE) {
                return trim($node->firstChild->nodeValue);
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
