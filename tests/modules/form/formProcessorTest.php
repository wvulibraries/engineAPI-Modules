<?php
class formProcesserTest extends PHPUnit_Framework_TestCase {
	private $db;
	private $processor;

	function setUp() {
		$this->db        = db::get('appDB')->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));
		$this->processor = new formProcessor('fieldBuilderTest', $this->db);
	}


	function test_setProcessorType(){
		$this->processor->setProcessorType('insert');
		$this->assertAttributeEquals(formBuilder::TYPE_INSERT, 'processorType', $this->processor);

		$this->processor->setProcessorType('update');
		$this->assertAttributeEquals(formBuilder::TYPE_UPDATE, 'processorType', $this->processor);

		$this->processor->setProcessorType('edit');
		$this->assertAttributeEquals(formBuilder::TYPE_EDIT,   'processorType', $this->processor);
	}
}

