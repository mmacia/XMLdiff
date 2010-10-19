<?php
/**
 * Copyright (C) 2009-2010 XmlDiff <http://codeup.net/xmldiff>
 * Moisés Maciá <mmacia@gmail.com>
 *
 * Licensed under the terms of the MIT License (see LICENSE)
 */
/* $Id: XmlDiff.php 2876 2009-07-08 14:15:24Z mmacia $ */

require_once dirname(__FILE__) . '/Match.php';
require_once dirname(__FILE__) . '/Delta.php';

class XmlDiff
{
	/**
	 * @var DOMDocument $doc1
	 */
	private $doc1;
	/**
	 * @var DOMDocument $doc2
	 */
	private $doc2;
	/**
	 * @var DOMXpath $xpath1
	 */
	private $xpath1;
	/**
	 * @var DOMXpath $xpath2
	 */
	private $xpath2;
	/**
	 * @var array $unmatched1 Unmatched nodes in tree1
	 */
	private $unmatched1 = array();
	/**
	 * @var array $unmatched2 Unmatched nodes in tree2
	 */
	private $unmatched2 = array();
	/**
	 * @var Delta $delta Delta actions necessary to transform doc1 in doc2
	 */
	private $delta;


	/**
	 * Default constructor
	 *
	 * @param mixed $file1 Original document
	 * @param mixed $file2 Modified document
	 */
	public function __construct($file1, $file2)
	{
		if ($file1 instanceof DOMDocument) {
			$this->doc1 = $file1;
		}
		else {
			$this->doc1 = new DOMDocument();
			$this->doc1->load($file1);
		}
		$this->xpath1 = new DOMXpath($this->doc1);

		if ($file2 instanceof DOMDocument) {
			$this->doc2 = $file2;
		}
		else {
			$this->doc2 = new DOMDocument();
			$this->doc2->load($file2);
		}
		$this->xpath2 = new DOMXpath($this->doc2);

		$this->delta = new Delta();
	}

	/**
	 * Default destructor
	 */
	public function __destruct()
	{
		unset($this->doc1);
		unset($this->doc2);
		unset($this->xpath1);
		unset($this->xpath2);
		unset($this->delta);
	}

	/**
	 * Get differences (delta) between tree1 and tree2
	 *
	 * @return Delta
	 */
	public function diff()
	{
		if (!is_null($this->doc1) && !is_null($this->doc2)) {
			// Phase 1:
			// fast match identical nodes in both trees
			$match = new Match();
			$match->fastMatch($this->doc1, $this->doc2);

			$this->unmatched1 = $match->getUnmatched(1);
			$this->unmatched2 = $match->getUnmatched(2);

			/*print_r('Phase #1: has been located ' . count($this->unmatched2) . ' diferent nodes');
			print_r($this->unmatched1);
			print_r($this->unmatched2);*/

			if (empty($this->unmatched1) && empty($this->unmatched2)) {
				return $this->delta; // both trees are identical, return empty delta
			}

			// Phase 2:
			// try to discover if unmatched nodes are actually an existent node
			// in doc1 with some changes in attributes or in its nodeValue property
			$this->diff_phase2($match);

			if (empty($this->unmatched1) && empty($this->unmatched2)) {
				return $this->delta;
			}
			//print_r((string)$this->delta);

			// Phase 3:
			// check if remaining unmatched nodes are in tree1 but not in tree2 and add
			// the corresponding "delete node" action in delta
			$this->diff_phase3();

			if (empty($this->unmatched1) && empty($this->unmatched2)) {
				return $this->delta;
			}
			/*print_r((string)$this->delta);
			print_r($this->unmatched1);
			print_r($this->unmatched2);*/

			// Phase 4:
			// check if remaining unmatched nodes are in tree2 but not in tree1 and add
			// the corresponding "insert node" action in delta
			$this->diff_phase4();
			//print_r((string)$this->delta);

			if (!empty($this->unmatched1) || !empty($this->unmatched2)) { // Remaining nodes -> Fatal error
				ob_start();
				echo $this->doc1->saveXML();
				echo "\n\n";
				echo $this->doc2->saveXML();
				$buff = ob_get_contents();
				ob_end_clean();
				throw new RangeException('There are remaining unmatched nodes!' . "\n" . $buff);
			}
		}

		return $this->delta;
	}

	/**
	 * Try to discover if unmatched nodes are actually an existent node
	 * in doc1 with some changes in attributes or in its nodeValue property in doc2
	 *
	 * @param Match $match instance of Match
	 */
	private function diff_phase2(Match $match)
	{
		/*print_r('Phase #2: ' . count($this->unmatched1) . ' remaining nodes to match.');
		print_r($this->unmatched1);
		print_r($this->unmatched2);*/
		if (empty($this->unmatched2)) {
			return;
		}

		foreach ($this->unmatched1 as $idx => $node) {
			$node_xpath = $node->getXPath();
			$isTextNode = (strpos($node_xpath, '/text()') !== false) ? true : false;
			$res1 = $this->xpath1->query($node->getXPath());
			$item = $res1->item(0);

			// is a text node inserted into an existent node -> modify existent node value
			if ($isTextNode && $res1->length == 1) {
				$origNode = new NodeInfo($item);
				$delta = $origNode->getDelta($node);
				$this->delta->addAction($delta);

				// delete from unmatched lists
				unset($this->unmatched1[$idx]);
				foreach ($this->unmatched2 as $idx2 => $node2) {
					$delta = $origNode->getDelta($node2);

					if (empty($delta)) {
						unset($this->unmatched2[$idx2]);
						break;
					}
				}
			}
			else if ($res1->length == 1 && !$match->isAlreadyMatched($item, 1)) {
				$origNode = new NodeInfo($item);
				$delta = $origNode->getDelta($node);

				if (!empty($delta)) {
					$this->delta->addAction($delta);
					unset($this->unmatched1[$idx]); // remove processed node from unmatched list
					unset($this->unmatched2[$idx]);
				}
			}
		}

		/*print_r($this->unmatched1);
		print_r($this->unmatched2);
		print_r($this->delta, true);*/
	}

	/**
	 * Check if remaining unmatched nodes are in tree1 but not in tree2 and add
	 * the corresponding "delete node" action in delta.
	 */
	private function diff_phase3()
	{
		// print_r('Phase #3: ' . count($this->unmatched2) . ' remaining nodes to match.' );
		if (empty($this->unmatched2)) {
			return;
		}

		$deleted_nodes_xpath = array();

		foreach ($this->unmatched2 as $idx => $node) {
			if ($node->getNodeType() === XML_ELEMENT_NODE) { // delete element node
				$delta = array(
					'action' => 'remove',
					'xpath'  => $node->getXPath(),
					'value'  => null);

				$this->delta->addAction(array($delta));
				$deleted_nodes_xpath[$node->getXPath()] = true;
				unset($this->unmatched2[$idx]);
			}
			else { // delete text node
				$parent_node_xpath = dirname($node->getXPath());
				// whole node is deleted, do nothing because current text node is also deleted
				if (isset($deleted_nodes_xpath[$parent_node_xpath])) {
					unset($this->unmatched2[$idx]);
				}
				// only node value is seted to empty value -> update the text value
				else {
					// remove /text() from xpath
					$_xpath = substr($node->getXPath(), 0, strlen($node->getXPath()) - 7);
					$delta = array(
						'action' => 'update',
						'xpath'  => $_xpath,
						'value'  => null);
					$this->delta->addAction(array($delta));
					unset($this->unmatched2[$idx]);
				}
			}
		}
	}

	/**
	 * Check if remaining unmatched nodes are in tree2 but not in tree1 and add
	 * the corresponding "insert node" action in delta
	 */
	private function diff_phase4()
	{
		//print_r('Phase #4: ' . count($this->unmatched1) . ' remaining nodes to match.');
		if (empty($this->unmatched1)) {
			return;
		}

		$doc = new DOMDocument();
		foreach ($this->unmatched1 as $idx => $node) {
			// get new node xpath expression
			preg_match( '/(\w+)\[(\d+)\]$/', $node->getXPath(), $regexp_matches ); // name[pos]
			if (!empty($regexp_matches)) {
				$pos  = $regexp_matches[2];
				$name = $regexp_matches[1];
				$newNodeXpath = dirname($node->getXPath());
				if ($pos > 1) {
					$newNodeXpath .= '/' . $name . '[' . ($pos - 1) . ']';
				}
			}
			else {
				$newNodeXpath = $node->getXPath();
			}

			if ($node->getNodeType() === XML_ELEMENT_NODE) { // insert element node
				$newNode = $doc->createElement($node->getName(), $node->getValue());

				foreach ($node->getAttributes() as $attr_name => $attr_value) {
					$newNode->setAttribute($attr_name, $attr_value);
				}

				if ($pos == 1) {
					$action = 'insert-child';
				} else {
					$action = 'insert-after';
				}

				$delta = array(
					'action' => $action,
					'xpath'  => $newNodeXpath,
					'value'  => $newNode);

				$this->delta->addAction(array($delta));
				unset($this->unmatched1[$idx]);
			}
			else { // insert text node
				if (strpos($newNodeXpath, '/text()') === false) {
					$newNodeXpath .= '/text()';
				}

				$delta = array(
					'action' => 'insert-textnode',
					'xpath'  => $newNodeXpath,
					'value'  => $node->getValue());

				$this->delta->addAction(array($delta));
				unset($this->unmatched1[$idx]);
			}
		}
	}
}
?>