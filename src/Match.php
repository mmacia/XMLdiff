<?php
/**
 * Copyright (C) 2009-2010 XmlDiff <http://codeup.net/xmldiff>
 * Moisés Maciá <mmacia@gmail.com>
 *
 * Licensed under the terms of the MIT License (see LICENSE)
 */
/* $Id: Match.php 2876 2009-07-08 14:15:24Z mmacia $ */

require_once dirname(__FILE__) . '/NodeInfo.php';

class Match
{
	/**
	 * @var array $traversal      Holds pre-order traversal sequence
	 */
	private $traversal    = array();
	/**
	 * @var array $unmatched1    Contains all different nodes between tree1 and tree2
	 */
	private $unmatched1   = array();
	/**
	 * @var array $matched1      Contains all matched nodes between tree1 and tree2
	 */
	private $matched1     = array();
	/**
	 * @var array $unmatched2    Contains different nodes wich there are in tree2 but not in tree1
	 */
	private $unmatched2   = array();
	/**
	 * @var array $matched2      Contains all matched nodes between tree2 and tree1
	 */
	private $matched2     = array();
	/**
	 * @var array $traversalTree1  Traversal preorder for tree1
	 */
	private $traversalTree1 = array();
	/**
	 * @var array $traversalTree2  Traversal preorder for tree2
	 */
	private $traversalTree2 = array();


	/**
	 * Default constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Perform Fast Match Algorithm
	 *
	 * @param DOMDocument $doc1 Original document
	 * @param DOMDocument $doc2 Modified document
	 * @return NodeSet
	 */
	public function fastMatch(DOMDocument $doc1, DOMDocument $doc2)
	{
		// prepare trees
		$doc1->normalizeDocument();
		$doc2->normalizeDocument();
		$this->traversalTree1 = $this->preOrder($doc1->documentElement);
		$this->traversalTree2 = $this->preOrder($doc2->documentElement);

		/*print_r($this->traversalTree1);
		print_r($this->traversalTree2,true);*/

		// Proceed bottom up matching nodes on tree1
		$this->unmatched1 = $this->traversalTree2; // contains different nodes

		foreach ($this->traversalTree1 as $idx1 => $t1) {
			if ($this->matchUniqueNodePath($t1, $this->unmatched1, $idx2)) {
				$this->matched1[] = $t1;
				unset($this->unmatched1[$idx2]);
			}
		}

		// Proceed bottom up matching nodes on tree2
		$this->unmatched2 = $this->traversalTree1;

		foreach ($this->traversalTree2 as $idx2 => $t2) {
			if ($this->matchUniqueNodePath($t2, $this->unmatched2, $idx1)) {
				$this->matched2[] = $t2;
				unset($this->unmatched2[$idx1]);
			}
		}

		/*print_r($this->unmatched1);
		print_r($this->unmatched2, true);*/

		// delete moved nodes in tree2 that exists in tree1
		foreach ($this->unmatched2 as $idx2 => $t2) {
			foreach ($this->unmatched1 as $idx1 => $t1) {
				if ($t1->getHashNode() == $t2->getHashNode()) { // detect moved nodes
					unset($this->unmatched1[$idx1]);
					unset($this->unmatched2[$idx2]);
					break;
				}
			}
		}

		unset($this->traversalTree1);
		unset($this->traversalTree2);
	}

	/**
	 * Try to find needle node in haystack tree with unique path to it, if search is success then mark needle
	 * node as matched and delete it from haystack.
	 *
	 * @param NodeInfo $needle
	 * @param array $haystack traversal tree
	 * @param int $idx Index node into traversal tree array
	 * @return boolean
	 */
	private function matchUniqueNodePath(NodeInfo &$needle, $haystack, &$idx)
	{
		$ret = false;
		foreach ($haystack as $idx => $t2) {
			if ($needle->equals($t2)) { // match considering xpath
				$ret = true;
				break;
			}
		}
		return $ret;
	}

	/**
	 * Return pre-order traversal tree from a given DOMDocument
	 *
	 * @param  mixed $domNode
	 * @param  int   $depth    Digg level
	 * @return array
	 */
	private function preOrder($domNode, $depth = 0)
	{
		if ($depth == 0) {
			$this->traversal = array(); // initialize traversal
		}
		$emptyNode = true;

		while (!is_null($domNode)) {
			$ni = new NodeInfo($domNode);
			$ni->setDepth($depth + 1);

			// push new node, skip empty text nodes
			if (!$ni->isEmptyNode()) {
				$this->traversal[] = $ni;
			}

			// digg tree
			if ($domNode->hasChildNodes()) {
				$this->preOrder($domNode->firstChild, $depth + 1);
			}

			// continue with next sibling
			$domNode = $domNode->nextSibling;
		}

		if ($depth == 0) { // return preorder tree
			return $this->traversal;
		}
	}

	/**
	 * Return the nodes that are differents in both trees
	 *
	 * @return array
	 */
	public function getUnmatched($tree)
	{
		if ($tree === 1) {
			return $this->unmatched1;
		}

		if ($tree === 2) {
			return $this->unmatched2;
		}
		return null;
	}

	/**
	 * Check if given node is already matched
	 *
	 * @param DOMElement $node
	 * @param int $tree In wich tree we should search?
	 * @return boolean
	 */
	public function isAlreadyMatched($node, $tree)
	{
		$ret   = false;
		$ni    = new NodeInfo($node);
		$depth = 0;

		while (!is_null($node)) {
			$node = $node->parentNode;
			++$depth;
		}
		$ni->setDepth($depth-1);

		foreach ($this->{'matched' . $tree} as $t) {
			$d = $ni->getDelta($t);
			if (empty($d)) {
				$ret = true;
				break;
			}
		}
		return $ret;
	}
}
?>