<?php

namespace Npf\Library;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Class XPathDom
 * @package Library
 */
final class XPathDom
{
    /**
     * @var DOMXPath Dom XPath Element Library
     */
    protected $domXPath = null;
    /**
     * @var DOMNodeList
     */
    protected $domNodeList = null;

    /**
     * @var DOMDocument Dom Document Library
     */
    private $dom = null;

    /**
     * Doma constructor.
     */
    final public function __construct()
    {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
    }

    /**
     * Load Html Content
     * @param $content
     * @return bool
     */
    final public function loadContent($content)
    {
        if ($this->dom->loadHTML($content)) {
            unset($this->domXPath);
            $this->domXPath = new DOMXPath($this->dom);
            $this->domXPath->registerNamespace("php", "http://php.net/xpath");
            $this->domXPath->registerPhpFunctions();
            return true;
        } else
            return false;
    }

    /**
     * Search Dom Element that content attr
     * @param $QueryTag
     * @param $Attr
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function searchTagAttr($QueryTag, $Attr, DOMNode $contextNode = null)
    {
        return $this->queryDom("{$QueryTag}[@{$Attr}]", $contextNode);
    }

    /**
     * Query a html path
     * @param $queryString
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function queryDom($queryString, DOMNode $contextNode = null)
    {
        $this->domNodeList = $this->domXPath->query($queryString, $contextNode);
        return $this->domNodeList;
    }

    /**
     * Search Dom Element that content attr
     * @param $QueryTag
     * @param $Attr
     * @param $Content
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function searchTagAttrValue($QueryTag, $Attr, $Content, DOMNode $contextNode = null)
    {
        $Content = str_replace("'", "\\'", $Content);
        return $this->queryDom("{$QueryTag}[contains(@{$Attr},'{$Content}')]", $contextNode);
    }

    /**
     * @param null $dom
     * @param DOMNode $contextNode
     * @return array|mixed|null
     */
    final public function getElementValue($dom, DOMNode $contextNode = null)
    {
        if (is_string($dom) && !empty($dom))
            $dom = $this->oneDom($dom, 'ASC', $contextNode);
        if ($dom instanceof DOMNode) {
            return $dom->nodeValue;
        } else
            return null;
    }

    /**
     * Query a first found dom
     * @param $queryString
     * @param string $order
     * @param DOMNode|null $contextNode
     * @return DOMNode|null
     */
    final public function oneDom($queryString, $order = 'ASC', DOMNode $contextNode = null)
    {
        $domNodeList = $this->domXPath->query($queryString, $contextNode);
        $position = 0;
        if (strtoupper($order) === 'DESC')
            $position = $domNodeList->length - 1;
        return $domNodeList instanceof DOMNodeList ? $domNodeList->item($position) : null;
    }

    /**
     * @param null $dom
     * @param $attr
     * @param DOMNode $contextNode
     * @return array|mixed|null
     */
    final public function getElementAttr($dom, $attr, DOMNode $contextNode = null)
    {
        if (is_string($dom) && !empty($dom))
            $dom = $this->oneDom($dom, 'ASC', $contextNode);
        if ($dom instanceof DOMElement) {
            return trim($dom->getAttribute($attr));
        } else
            return null;
    }

    /**
     * @param null $doms
     * @param DOMNode|null $contextNode
     * @return array|mixed|null
     */
    final public function getElementListValues($doms = null, DOMNode $contextNode = null)
    {
        if (is_string($doms) && !empty($doms))
            $doms = $this->queryDom($doms, $contextNode);
        if ($doms instanceof DOMNodeList === false)
            $doms = $this->domNodeList;
        if ($doms instanceof DOMNodeList === true) {
            $values = [];
            foreach ($doms as $dom)
                $values[] = trim($dom->nodeValue);
            if (count($values) === 1)
                return reset($values);
            else
                return $values;
        } else
            return null;
    }

    /**
     * Get Node List Elements Values
     * @param $Name
     * @param DOMNodeList|string|null $queryDoms
     * @param DOMNode $contextNode
     * @return array|mixed|null
     * @internal DOMNode $dom
     */
    final public function getElementsListAttrValues($Name, $queryDoms = null, DOMNode $contextNode = null)
    {
        if (is_string($queryDoms) && !empty($queryDoms))
            $queryDoms = $this->queryDom($queryDoms, $contextNode);
        if ($queryDoms instanceof DOMNodeList === false)
            $queryDoms = $this->domNodeList;
        $values = [];
        if ($queryDoms instanceof DOMNodeList === true) {
            foreach ($queryDoms as $Dom) {
                if ($Dom instanceof DOMElement)
                    $values[] = trim($Dom->getAttribute($Name));
            }
            if (count($values) === 1)
                return reset($values);
            else
                return $values;
        } else
            return null;
    }

    /**
     * Get Dom Node list by tag name
     * @param string $tagName
     * @return DOMNodeList|null
     */
    final public function getElementByTagName($tagName)
    {
        return $this->dom->getElementsByTagName($tagName);
    }

    /**
     * Get Dom Element by Id
     * @param string $id
     * @return DOMElement|null
     */
    final public function getElementById($id)
    {
        return $this->dom->getElementById($id);
    }

    /**
     * Count Query of dom
     * @param null $queryDoms
     * @param DOMNode $contextNode
     * @return mixed
     */
    final public function countDom($queryDoms = null, DOMNode $contextNode = null)
    {
        return $this->domXPath->evaluate("count({$queryDoms})", $contextNode);
    }

    /**
     * Sum Dom Values
     * @param null $queryDoms
     * @param DOMNode $contextNode
     * @return mixed
     */
    final public function sumDomValue($queryDoms = null, DOMNode $contextNode = null)
    {
        return $this->domXPath->evaluate("sum({$queryDoms})", $contextNode);
    }

    /**
     * Evaluate DOm
     * @param null $queryDoms
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    final public function evaluate($queryDoms = null, DOMNode $contextNode = null)
    {
        return $this->domXPath->evaluate($queryDoms, $contextNode);
    }
}