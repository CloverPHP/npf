<?php

namespace Npf\Library;

use DOMDocument;
use DOMImplementation;
use DOMNamedNodeMap;
use DOMNode;
use Exception;
use Npf\Exception\InternalError;

/**
 * Class XPathDom
 * @package Library
 */
final class Xml
{
    /**
     * @var string
     */
    private static $encoding = 'UTF-8';

    /**
     * @var DOMDocument
     */
    private static $xml = null;

    /**
     * Initialize the root XML node [optional].
     *
     * @param string $version
     * @param string $encoding
     * @param bool $standalone
     * @param bool $format_output
     */
    public static function init($version = '1.0', $encoding = 'utf-8', $standalone = false, $format_output = true)
    {
        self::$xml = new DomDocument($version, $encoding);
        self::$xml->xmlStandalone = $standalone;
        self::$xml->formatOutput = $format_output;
        self::$encoding = $encoding;
    }

    /**
     * Convert an Array to XML.
     * @param string $node_name - name of the root node to be converted
     * @param array $arr - array to be converted
     * @param array $docType - optional docType
     * @return DomDocument
     */
    public static function createXML($node_name, $arr = [], $docType = [])
    {
        $xml = self::getXMLRoot();
        // BUG 008 - Support <!DOCTYPE>
        if ($docType) {
            $xml->appendChild(
                (new DOMImplementation())
                    ->createDocumentType(
                        !empty($docType['name']) ? $docType['name'] : '',
                        !empty($docType['publicId']) ? $docType['publicId'] : '',
                        !empty($docType['systemId']) ? $docType['systemId'] : ''
                    )
            );
        }
        $xml->appendChild(self::convert2Xml($node_name, $arr));
        self::$xml = null;
        return $xml;
    }

    /**
     * Convert an XML to Array.
     * @param string|DOMDocument $inputXml
     * @return array
     * @throws Exception
     */
    public static function createArray($inputXml)
    {
        $xml = self::getXMLRoot();
        if (is_string($inputXml)) {
            try {
                $xml->loadXML($inputXml);
                if (!is_object($xml) || empty($xml->documentElement)) {
                    throw new InternalError('Document is empty');
                }
            } catch (Exception $ex) {
                throw new InternalError('[XML2Array] Error parsing the XML string.' . PHP_EOL . $ex->getMessage());
            }
        } elseif (is_object($inputXml)) {
            if (get_class($inputXml) != 'DOMDocument') {
                throw new InternalError('[XML2Array] The input XML object should be of type: DOMDocument.');
            }
            $xml = self::$xml = $inputXml;
        } else {
            throw new InternalError('[XML2Array] Invalid input');
        }

        // Bug 008 - Support <!DOCTYPE>.
        $docType = $xml->doctype;
        if ($docType) {
            $array['@docType'] = [
                'name' => $docType->name,
                'entities' => self::getNamedNodeMapAsArray($docType->entities),
                'notations' => self::getNamedNodeMapAsArray($docType->notations),
                'publicId' => $docType->publicId,
                'systemId' => $docType->systemId,
                'internalSubset' => $docType->internalSubset,
            ];
        }

        $array[$xml->documentElement->tagName] = self::convert2Array($xml->documentElement);
        self::$xml = null;    // clear the xml node in the class for 2nd time use.

        return $array;
    }


    /**
     * Convert an Array to XML.
     *
     * @param string $node_name - name of the root node to be converted
     * @param array $arr - array to be converted
     *
     * @return DOMNode
     *
     * @throws Exception
     */
    private static function convert2Xml($node_name, $arr = [])
    {
        //print_arr($node_name);
        $xml = self::getXMLRoot();
        $node = $xml->createElement($node_name);

        if (is_array($arr)) {
            // get the attributes first.;
            if (array_key_exists('@attributes', $arr) && is_array($arr['@attributes'])) {
                foreach ($arr['@attributes'] as $key => $value) {
                    if (!self::isValidTagName($key)) {
                        throw new InternalError('[Array2XML] Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $node_name);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($arr['@attributes']); //remove the key from the array once done.
            }

            // check if it has a value stored in @value, if yes store the value and return
            // else check if its directly stored as string
            if (array_key_exists('@value', $arr)) {
                $node->appendChild($xml->createTextNode(self::bool2str($arr['@value'])));
                unset($arr['@value']);    //remove the key from the array once done.
                //return from recursion, as a note with value cannot have child nodes.
                return $node;
            } elseif (array_key_exists('@cdata', $arr)) {
                $node->appendChild($xml->createCDATASection(self::bool2str($arr['@cdata'])));
                unset($arr['@cdata']);    //remove the key from the array once done.
                //return from recursion, as a note with cdata cannot have child nodes.
                return $node;
            }
        }

        //create subnodes using recursion
        if (is_array($arr)) {
            // recurse to get the node for that key
            foreach ($arr as $key => $value) {
                if (!self::isValidTagName($key)) {
                    throw new InternalError('[Array2XML] Illegal character in tag name. tag: ' . $key . ' in node: ' . $node_name);
                }
                if (is_array($value) && is_numeric(key($value))) {
                    // MORE THAN ONE NODE OF ITS KIND;
                    // if the new array is numeric index, means it is array of nodes of the same kind
                    // it should follow the parent key name
                    foreach ($value as $k => $v) {
                        $node->appendChild(self::convert2Xml($key, $v));
                    }
                } else {
                    // ONLY ONE NODE OF ITS KIND
                    $node->appendChild(self::convert2Xml($key, $value));
                }
                unset($arr[$key]); //remove the key from the array once done.
            }
        }

        // after we are done with all the keys in the array (if it is one)
        // we check if it has any text value, if yes, append it.
        if (!is_array($arr)) {
            $node->appendChild($xml->createTextNode(self::bool2str($arr)));
        }

        return $node;
    }


    /**
     * Convert an XML to an Array.
     * @param DOMNode $node - XML as a string or as an object of DOMDocument
     * @return array
     */
    private static function convert2Array(DOMNode $node)
    {
        $output = [];

        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
                $output['@cdata'] = trim($node->textContent);
                break;

            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;

            case XML_ELEMENT_NODE:

                // for each child node, call the covert function recursively
                for ($i = 0, $m = $node->childNodes->length; $i < $m; ++$i) {
                    $child = $node->childNodes->item($i);
                    $v = self::convert2Array($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;

                        // assume more nodes of same kind are coming
                        if (!array_key_exists($t, $output)) {
                            $output[$t] = [];
                        }
                        $output[$t][] = $v;
                    } else {
                        //check if it is not an empty node
                        if (!empty($v) || $v === '0') {
                            $output = $v;
                        }
                    }
                }

                if (is_array($output)) {
                    // if only one node of its kind, assign it directly instead if array($value);
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1) {
                            $output[$t] = $v[0];
                        }
                    }
                    if (empty($output)) {
                        //for empty nodes
                        $output = '';
                    }
                }

                // loop through the attributes and collect them
                if ($node->attributes->length) {
                    $a = [];
                    foreach ($node->attributes as $attrName => $attrNode) {
                        $a[$attrName] = $attrNode->value;
                    }
                    // if its an leaf node, store the value in @value instead of directly storing it.
                    if (!is_array($output)) {
                        $output = ['@value' => $output];
                    }
                    $output['@attributes'] = $a;
                }
                break;
        }

        return $output;
    }

    /**
     * Get the root XML node, if there isn't one, create it.
     *
     * @return DOMDocument
     */
    private static function getXMLRoot()
    {
        if (empty(self::$xml)) {
            self::init();
        }

        return self::$xml;
    }

    /**
     * Get string representation of boolean value.
     *
     * @param mixed $v
     *
     * @return string
     */
    private static function bool2str($v)
    {
        //convert boolean to text value.
        $v = $v === true ? 'true' : $v;
        $v = $v === false ? 'false' : $v;

        return $v;
    }

    /**
     * @param DOMNamedNodeMap $namedNodeMap
     *
     * @return array|null
     */
    private static function getNamedNodeMapAsArray(DOMNamedNodeMap $namedNodeMap)
    {
        $result = null;
        if ($namedNodeMap->length) {
            foreach ($namedNodeMap as $key => $entity) {
                $result[$key] = $entity;
            }
        }

        return $result;
    }

    /**
     * Check if the tag name or attribute name contains illegal characters
     * Ref: http://www.w3.org/TR/xml/#sec-common-syn.
     * @param string $tag
     * @return bool
     */
    private static function isValidTagName($tag)
    {
        $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';

        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }
}