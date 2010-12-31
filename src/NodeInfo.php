<?php
/**
 * Copyright (C) 2009-2010 XmlDiff <http://codeup.net/xmldiff>
 * Moisés Maciá <mmacia@gmail.com>
 *
 * Licensed under the terms of the MIT License (see LICENSE)
 */
/* $Id: NodeInfo.php 2876 2009-07-08 14:15:24Z mmacia $ */

class NodeInfo
{
	/**
	 * @var int $deth Node depth level
	 */
	private $depth;
	/**
	 * @var string $name Node tag name
	 */
	private $name;
	/**
	 * @var string $value Node value
	 */
	private $value;
	/**
	 * @var array $attributes Node attributes
	 */
	private $attributes  = array();
	/**
	 * @var int $nodeType DOM Node type
	 */
	private $nodeType;
	/**
	 * @var boolean $emptyNode Indicate if the current node is an empty text node
	 */
	private $emptyNode   = false;
	/**
	 * @var string $xpath XPath expression thats points to current node
	 */
	private $xpath;
	/**
	 * @var string $hash_branch Unique Id that identifies the current node and its path in tree
	 */
	private $hash_branch;
	/**
	 * @var string $hash_node Unique Id that identifies the current node and its contents
	 */
	private $hash_node;
	/**
	 * @var DOMElement $domNode Original DOM element
	 */
	private $domNode;


	/**
	 * Default constructor
	 *
	 * @param mixed $node
	 */
	public function __construct($node)
	{
		if (!($node instanceof DOMElement || $node instanceof DOMText)) {
			throw new Exception('You cannot construct a NodeInfo with a "' . get_class($node)
				. '" object, only works with DOM elements!');
		}

		$this->name     = $node->nodeName;
		$this->nodeType = $node->nodeType;

		switch ($this->nodeType) {
			case XML_TEXT_NODE:
				$val = trim($node->nodeValue);

				if (!empty($val)) {
					$this->value = $val;
				} else {
					$this->emptyNode = true;
				}
				break;

			case XML_ELEMENT_NODE:
				if ($node->hasAttributes()) {
					$elementarray = array();
					$attributes = $node->attributes;

					foreach ($attributes as $index => $domObj) {
						$elementarray[$domObj->name] = $domObj->value;
					}
					ksort($elementarray);
					$this->attributes = $elementarray;
				}
		}

		if (!$this->emptyNode) {
			$this->domNode = $node;
			$this->xpath = self::getNodeXPath($node);
			asort($this->attributes);
			$this->hash_node = sha1($this->value . $this->depth . $this->nodeType . $this->name
				. serialize($this->attributes));
			$this->hash_branch = sha1($this->hash_node . $this->xpath);
		}
	}

	/**
	 * Get node name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Get node value
	 *
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Set node depth level
	 * Root node has 0 level
	 *
	 * @param int $depth
	 */
	public function setDepth($depth)
	{
		$this->depth = (int)$depth;
	}

	/**
	 * Get node attributes
	 *
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * Get DOM node type
	 *
	 * @return int
	 */
	public function getNodeType()
	{
		return $this->nodeType;
	}

	/**
	 * Get node XPath expression
	 *
	 * @param string $xpath
	 */
	public function getXPath()
	{
		return $this->xpath;
	}

	/**
	 * Return a unique Id for current node.
	 * The ID is computed with all node metadata
	 *
	 * @return string
	 */
	public function getHashNode()
	{
		return $this->hash_node;
	}

	/**
	 * Return a unique Id for current node and its path into the tree
	 *
	 * @return string
	 */
	public function getHashBranch()
	{
		return $this->hash_branch;
	}

	/**
	 * Determine if two NodeInfo objects are equals
	 *
	 * @return boolean
	 */
	public function equals(NodeInfo $obj)
	{
		return $this->getHashBranch() === $obj->getHashBranch();
	}

	/**
	 * Is empty node
	 *
	 * @return boolean
	 */
	public function isEmptyNode()
	{
		return $this->emptyNode;
	}

	/**
	 * Get a XPath expression needed to access current node
	 *
	 * @param DOMElement $node
	 * @return string XPath expression
	 */
	private static function getNodeXPath($node)
	{
		// Get the index for the current node by looping through the siblings.
		$parentNode = $node->parentNode;
		if (!is_null($parentNode)) {
			$nodeIndex = 0;

			do {
				$testNode = $parentNode->childNodes->item($nodeIndex);
				$nodeName = $testNode->nodeName;
				$nodeIndex++;

				// HACK : Here we create a counter based on the node
				//  name of the test node to use in the XPath.
				if (!isset($$nodeName)) $$nodeName = 1;
				else $$nodeName++;

				// Failsafe return value.
				if ($nodeIndex > $parentNode->childNodes->length) return "/";
			} while (!$node->isSameNode($testNode));

			// Recursively get the XPath for the parent.
			if ($node->nodeType === XML_TEXT_NODE) {
				return self::getNodeXPath($parentNode) . "/text()";
			} else {
				return self::getNodeXPath($parentNode) . "/{$node->nodeName}[{$$nodeName}]";
			}
		}
		else {
			// Hit the root node!  Note that the slash is added when
			//  building the XPath, so we return just an empty string.
			return "";
		}
    }

	/**
	 * Get delta actions to transform current node into given node
	 *
	 * @param DOMNode $n Node to compare
	 * @return array
	 */
	public function getDelta(NodeInfo $n)
	{
		$delta = array();

		// check changes in node value
		if ($this->value !== $n->getValue()) {
			$delta[] = array(
				'action' => 'update',
				'xpath'  => $this->getXPath(),
				'value'  => $n->getValue());
		}

		$total       = count($this->attributes);
		$_attributes = $n->getAttributes();
		$_total      = count($_attributes);

		// check changes in attributes
		if (($total > 0) || ($_total > 0)) {
			// compare modified node against original for changed attributes
			// in order to detect new and modified attributes
			$attr_diff          = array_diff_assoc($_attributes, $this->attributes);
			$updated_attribute  = array(); // remember if node attribute is already updated

			if (!empty($attr_diff)) {
				foreach ($attr_diff as $attr_name => $attr_value) {
					if (!isset($this->attributes[$attr_name])) { // insert new attribute
						$delta[] = array(
							'action' => 'append',
							'xpath'  => $this->getXPath(),
							'value'  => $attr_name . '="' . $attr_value . '"');
					}
					else { // update attribute value
						$delta[] = array(
							'action' => 'update',
							'xpath'  => $this->getXPath() . '/@' . $attr_name,
							'value'  => $attr_value);
						$updated_attribute[$attr_name] = true;
					}
				}
			}

			// compare original node against modified for changed attributes
			// in order to detect deleted attributes
			$attr_diff   = array_diff_assoc($this->attributes, $_attributes);

			if (!empty($attr_diff)) {
				foreach ($attr_diff as $attr_name => $attr_value) {
					// delete node attribute
					if (isset($this->attributes[$attr_name])
						&& !isset($updated_attribute[$attr_name])) {
						$delta[] = array(
							'action' => 'remove',
							'xpath'  => $this->getXPath() . '/@' . $attr_name,
							'value'  => null);
					}
				}
			}
		}
		return $delta;
	}
}
?>
