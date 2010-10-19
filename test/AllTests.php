<?php
require_once 'PHPUnit/Framework/TestSuite.php';
require_once 'XmlDiffTest.php';

class XmlDiff_AllTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('XmlDiff');
		$suite->addTestSuite('XmlDiffTest');
		return $suite;
	}
}
?>