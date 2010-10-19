<?php
class XmlDiffTest extends PHPUnit_Framework_TestCase
{
	private $doc_path;

	public function __construct( )
	{
	}


	protected function setUp()
	{
		$this->doc_path = dirname(__FILE__) . '/xml/';
	}

	protected function tearDown()
	{
	}


	public function testEqualDocuments ()
	{
		$f1 = $this->doc_path . 'test1a.xml';
		$f2 = $this->doc_path . 'test1b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = (string)$xdiff->diff();

		$this->assertEquals(true, empty($delta), 'fails diffing two identical documents');
	}


	public function testAttributesModified ()
	{
		$f1 = $this->doc_path . 'test2a.xml';
		$f2 = $this->doc_path . 'test2b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected = '[update, /KeywordsDocument[1]/Keyword[3]/@att2, cambiado]';

		$this->assertEquals($expected, $delta,
			'fails diffing two documents with a modified attribute');
	}


	public function testAttributesInserted ()
	{
		$f1 = $this->doc_path . 'test3a.xml';
		$f2 = $this->doc_path . 'test3b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected = '[append, /KeywordsDocument[1]/Keyword[3], att2="nuevo"]';

		$this->assertEquals($expected, $delta, 'fails inserting a new attribute');
	}


	public function testAttributesDeleted ()
	{
		$f1 = $this->doc_path . 'test4a.xml';
		$f2 = $this->doc_path . 'test4b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected = '[remove, /KeywordsDocument[1]/KeywordGroup[1]/Keyword[1]/@delete]';

		$this->assertEquals($expected, $delta, 'fails removing an attribute');
	}


	public function testNodeValueModified ()
	{
		$f1 = $this->doc_path . 'test5a.xml';
		$f2 = $this->doc_path . 'test5b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected = '[update, /KeywordsDocument[1]/KeywordGroup[1]/Keyword[1]/text(), cambiado]';

		$this->assertEquals($expected, $delta, 'fails updating a node value');
	}


	public function testNodeDeleted ()
	{
		$f1 = $this->doc_path . 'test6a.xml';
		$f2 = $this->doc_path . 'test6b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected = '[remove, /KeywordsDocument[1]/Keyword[5]]';

		$this->assertEquals($expected, $delta, 'fails removing a node');
	}


	public function testNodeInserted ()
	{
		$f1 = $this->doc_path . 'test7a.xml';
		$f2 = $this->doc_path . 'test7b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected  = "[insert-after, /KeywordsDocument[1]/Keyword[4], {DOMElement: Keyword}]\n"
			. "[insert-textnode, /KeywordsDocument[1]/Keyword[5]/text(), blabla]";

		$this->assertEquals($expected, $delta, 'fails inserting a new node');
	}


	public function testRootNodeAttributes ()
	{
		$f1 = $this->doc_path . 'test8a.xml';
		$f2 = $this->doc_path . 'test8b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = trim( (string)$xdiff->diff() );
		$expected  = "[update, /root[1]/@version, 2.0]\n"
			. '[append, /root[1], att="hola"]';

		$this->assertEquals($expected, $delta, 'fails updating root node');
	}


	public function testGetEditScript ()
	{
		$f1 = $this->doc_path . 'test8a.xml';
		$f2 = $this->doc_path . 'test8b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = $xdiff->diff();
		$es = $delta->getEditScript();
		$expected  = array(
			array(
				'action' => 'update',
				'xpath'  => '/root[1]/@version',
				'value'  => '2.0'
			),
			array(
				'action' => 'append',
				'xpath'  => '/root[1]',
				'value'  => 'att="hola"'
			)
		);

		$this->assertEquals($expected, $es, 'fails getting edit script');
	}

	public function testUpdateToEmptyValue()
	{
		$f1 = $this->doc_path . 'test9a.xml';
		$f2 = $this->doc_path . 'test9b.xml';

		$xdiff = new XmlDiff($f1, $f2);
		$delta = $xdiff->diff();
		$es = $delta->getEditScript();
		$expected  = array(
			array(
				'action' => 'update',
				'xpath'  => '/root[1]/tag[2]',
				'value'  => null
			)
		);

		$this->assertEquals($expected, $es, 'fails updating node value to null');
	}
}
?>