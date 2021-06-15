<?php
declare(strict_types=1);

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
    protected DOMXPath $domXPath;
    /**
     * @var DOMNodeList
     */
    protected DOMNodeList $domNodeList;

    /**
     * @var DOMDocument Dom Document Library
     */
    private DOMDocument $dom;

    /**
     * XPathDom constructor.
     */
    final public function __construct()
    {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
    }

    /**
     * Load Html Content
     * @param string $content
     * @return self
     */
    final public function loadContent(string $content): self
    {
        if ($this->dom->loadHTML($content)) {
            unset($this->domXPath);
            $this->domXPath = new DOMXPath($this->dom);
            $this->domXPath->registerNamespace("php", "http://php.net/xpath");
            $this->domXPath->registerPhpFunctions();
        }
        return $this;
    }

    /**
     * Search Dom Element that content attr
     * @param string $queryTag
     * @param string $attribute
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function searchTagAttr(string $queryTag, string $attribute, ?DOMNode $contextNode = null): ?DOMNodeList
    {
        return $this->queryDom("{$queryTag}[@{$attribute}]", $contextNode);
    }

    /**
     * Query a html path
     * @param string $queryString
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function queryDom(string $queryString, DOMNode $contextNode = null): ?DOMNodeList
    {
        $this->domNodeList = $this->domXPath->query($queryString, $contextNode);
        return $this->domNodeList;
    }

    /**
     * Search Dom Element that content attr
     * @param string $queryString
     * @param string $attribute
     * @param string $content
     * @param DOMNode|null $contextNode
     * @return DOMNodeList|null
     */
    final public function searchTagAttrValue(string $queryString,
                                             string $attribute,
                                             string $content,
                                             DOMNode $contextNode = null): ?DOMNodeList
    {
        $content = str_replace("'", "\\'", $content);
        return $this->queryDom("{$queryString}[contains(@{$attribute},'{$content}')]", $contextNode);
    }

    /**
     * @param string|DOMNode $dom
     * @param DOMNode|null $contextNode
     * @return ?string
     */
    final public function getElementValue(string|DOMNode $dom, DOMNode $contextNode = null): ?string
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
     * @param string $queryString
     * @param string $order
     * @param DOMNode|null $contextNode
     * @return DOMNode|null
     */
    final public function oneDom(string $queryString,
                                 string $order = 'ASC',
                                 DOMNode $contextNode = null): ?DOMNode
    {
        $domNodeList = $this->domXPath->query($queryString, $contextNode);
        $position = 0;
        if (strtoupper($order) === 'DESC')
            $position = $domNodeList->length - 1;
        return $domNodeList instanceof DOMNodeList ? $domNodeList->item($position) : null;
    }

    /**
     * @param string|DOMNode $dom
     * @param string $attr
     * @param DOMNode|null $contextNode
     * @return string|null
     */
    final public function getElementAttr(string|DOMNode $dom,
                                         string $attr,
                                         DOMNode $contextNode = null): ?string
    {
        if (is_string($dom) && !empty($dom))
            $dom = $this->oneDom($dom, 'ASC', $contextNode);
        if ($dom instanceof DOMElement) {
            return trim($dom->getAttribute($attr));
        } else
            return null;
    }

    /**
     * @param string|DOMNodeList $domList
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    final public function getElementListValues(string|DOMNodeList $domList, DOMNode $contextNode = null): mixed
    {
        if (is_string($domList) && !empty($domList))
            $domList = $this->queryDom($domList, $contextNode);
        if ($domList instanceof DOMNodeList === false)
            $domList = $this->domNodeList;
        if ($domList instanceof DOMNodeList === true) {
            $values = [];
            foreach ($domList as $dom)
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
     * @param string $name
     * @param DOMNodeList|string|null $queryString
     * @param DOMNode|null $contextNode
     * @return mixed
     * @internal DOMNode $dom
     */
    final public function getElementsListAttrValues(string $name,
                                                    string|DOMNodeList|null $queryString = null,
                                                    ?DOMNode $contextNode = null): mixed
    {
        if (is_string($queryString) && !empty($queryString))
            $queryString = $this->queryDom($queryString, $contextNode);
        if ($queryString instanceof DOMNodeList === false)
            $queryString = $this->domNodeList;
        $values = [];
        if ($queryString instanceof DOMNodeList === true) {
            foreach ($queryString as $Dom) {
                if ($Dom instanceof DOMElement)
                    $values[] = trim($Dom->getAttribute($name));
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
    final public function getElementByTagName(string $tagName): ?DOMNodeList
    {
        return $this->dom->getElementsByTagName($tagName);
    }

    /**
     * Get Dom Element by Id
     * @param string $id
     * @return DOMElement|null
     */
    final public function getElementById(string $id): ?DOMElement
    {
        return $this->dom->getElementById($id);
    }

    /**
     * Count Query of dom
     * @param string $queryString
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    final public function countDom(string $queryString, DOMNode $contextNode = null): mixed
    {
        return $this->domXPath->evaluate("count({$queryString})", $contextNode);
    }

    /**
     * Sum Dom Values
     * @param string $queryString
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    final public function sumDomValue(string $queryString, DOMNode $contextNode = null): mixed
    {
        return $this->domXPath->evaluate("sum({$queryString})", $contextNode);
    }

    /**
     * Evaluate DOm
     * @param string $queryString
     * @param DOMNode|null $contextNode
     * @return mixed
     */
    final public function evaluate(string $queryString, DOMNode $contextNode = null): mixed
    {
        return $this->domXPath->evaluate($queryString, $contextNode);
    }
}