<?php
declare(strict_types=1);

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
     * Get the root XML node, if there isn't one, create it.
     *
     * @param string $version
     * @param string $encoding
     * @param bool $standalone
     * @param bool $format_output
     * @return DOMDocument
     */
    private static function getXMLRoot(string $version = '1.0',
                                       string $encoding = 'utf-8',
                                       bool $standalone = false,
                                       bool $format_output = true): DOMDocument
    {
        $xml = new DomDocument($version, $encoding);
        $xml->xmlStandalone = $standalone;
        $xml->formatOutput = $format_output;
        return $xml;
    }

    /**
     * Convert an Array to XML.
     * @param string $rootName - name of the root node to be converted
     * @param array $content - array to be converted
     * @param array $docType - optional docType
     * @return DomDocument
     * @throws Exception
     */
    public static function createXML(string $rootName, array $content = [], array $docType = []): DOMDocument
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
        $xml->appendChild(self::convert2Xml($rootName, $content));
        return $xml;
    }

    /**
     * Convert an XML to Array.
     * @param string|DOMDocument $inputXml
     * @return array
     * @throws Exception
     */
    public static function createArray(string|DOMDocument $inputXml): array
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
        } elseif ($inputXml instanceof DOMDocument)
            $xml = $inputXml;
        else
            throw new InternalError('[XML2Array] Invalid input');

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
        return $array;
    }


    /**
     * Convert an Array to XML.
     *
     * @param string $rootName - name of the root node to be converted
     * @param array $content - array to be converted
     *
     * @return DOMNode
     *
     * @throws Exception
     */
    private static function convert2Xml(string $rootName, array $content = []): DOMNode
    {
        //print_arr($node_name);
        $xml = self::getXMLRoot();
        $node = $xml->createElement($rootName);

        if (is_array($content)) {
            // get the attributes first.;
            if (array_key_exists('@attributes', $content) && is_array($content['@attributes'])) {
                foreach ($content['@attributes'] as $key => $value) {
                    if (!self::isValidTagName($key)) {
                        throw new InternalError('[Array2XML] Illegal character in attribute name. attribute: ' . $key . ' in node: ' . $rootName);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($content['@attributes']); //remove the key from the array once done.
            }

            // check if it has a value stored in @value, if yes store the value and return
            // else check if its directly stored as string
            if (array_key_exists('@value', $content)) {
                $node->appendChild($xml->createTextNode(self::bool2str($content['@value'])));
                unset($content['@value']);    //remove the key from the array once done.
                //return from recursion, as a note with value cannot have child nodes.
                return $node;
            } elseif (array_key_exists('@cdata', $content)) {
                $node->appendChild($xml->createCDATASection(self::bool2str($content['@cdata'])));
                unset($content['@cdata']);    //remove the key from the array once done.
                //return from recursion, as a note with cdata cannot have child nodes.
                return $node;
            }
        }

        //create subnodes using recursion
        if (is_array($content)) {
            // recurse to get the node for that key
            foreach ($content as $key => $value) {
                if (!self::isValidTagName($key)) {
                    throw new InternalError('[Array2XML] Illegal character in tag name. tag: ' . $key . ' in node: ' . $rootName);
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
                unset($content[$key]); //remove the key from the array once done.
            }
        }

        // after we are done with all the keys in the array (if it is one)
        // we check if it has any text value, if yes, append it.
        if (!is_array($content))
            $node->appendChild($xml->createTextNode(self::bool2str($content)));

        return $node;
    }


    /**
     * Convert an XML to an Array.
     * @param DOMNode $node - XML as a string or as an object of DOMDocument
     * @return array|string
     */
    private static function convert2Array(DOMNode $node): array|string
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
     * Get string representation of boolean value.
     * @param mixed $value
     * @return string
     */
    private static function bool2str(bool $value): string
    {
        //convert boolean to text value.
        return $value === true ? 'true' : 'false';
    }

    /**
     * @param DOMNamedNodeMap $namedNodeMap
     * @return array
     */
    private static function getNamedNodeMapAsArray(DOMNamedNodeMap $namedNodeMap): array
    {
        $result = [];
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
    private static function isValidTagName(string $tag): bool
    {
        $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';

        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }
}