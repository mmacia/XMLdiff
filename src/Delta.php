<?php
/**
 * Copyright (C) 2009-2010 XmlDiff <http://codeup.net/xmldiff>
 * Moisés Maciá <mmacia@gmail.com>
 *
 * Licensed under the terms of the MIT License (see LICENSE)
 */
/* $Id: Delta.php 2876 2009-07-08 14:15:24Z mmacia $ */

class Delta
{
	/**
	 * Edit script actions
	 */
	private $actions = array();


	/**
	 * Default constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Add a new action into edit script
	 *
	 * @param array $action
	 */
	public function addAction($action)
	{
		if (is_array($action)) {
			foreach ($action as $a) {
				$this->actions[] = $a;
			}
		}
	}

	/**
	 * Get Edit Script actions
	 *
	 * @return array
	 */
	public function getEditScript()
	{
		return $this->actions;
	}

	/**
	 * Override object to string cast
	 */
	public function __toString()
	{
		$str = "";
		foreach ($this->actions as $a) {
			if (!is_null($a['value']) && is_string($a['value'])) {
				$val = ', ' . $a['value'];
			} else if ($a['value'] instanceof DOMElement) {
				$val = ', {DOMElement: ' . $a['value']->nodeName . '}';
			} else {
				$val = '';
			}

			$str .= '[' . $a['action'] . ', ' . $a['xpath'] . $val . ']' . "\n";
		}
		return $str;
	}
}
?>