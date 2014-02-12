<?php


class formBuilderTest extends PHPUnit_Framework_TestCase{
	/**
	 * @var formBuilder
	 */
	private $form;

	function setUp(){
		$this->form = formBuilder::createForm();
	}

	// -------------------------------------------------

	function test_itCountsTheNumberOfFields_noFields(){
		$this->assertCount(0, $this->form);
	}

	function test_itCountsTheNumberOfFields_withFields(){
		$this->assertCount(0, $this->form);

		$this->form->addField(array('name' => 'foo'));
		$this->assertCount(1, $this->form);

		$this->form->addField(array('name' => 'bar'));
		$this->assertCount(2, $this->form);
	}

	function test_reset(){
		$this->assertCount(0, $this->form);
		$this->form->addField(array('name' => 'foo'));
		$this->form->addField(array('name' => 'bar'));
		$this->assertCount(2, $this->form);
		$this->form->reset();
		$this->assertCount(0, $this->form);
		$this->assertAttributeEmpty('fields', $this->form);
		$this->assertAttributeEmpty('fieldLabels', $this->form);
		$this->assertAttributeEmpty('fieldIDs', $this->form);
	}

	function test_itStartsOffWithNoFields(){
		$this->assertEmpty(sizeof($this->form));
	}

	function test_displayInsertForm_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insert.html');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');
		$formOutput              = $this->form->displayInsertForm($options);
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->displayInsertForm();
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->displayInsertForm(NULL);
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_templateBlob(){
		$options    = array('template' => 'Test String');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertEquals('Test String', $formOutput);
	}

	function test_setPrimaryFields_errorCases(){
		$this->assertFalse($this->form->setPrimaryField('foo'), 'Fails undefined field');
		$this->assertFalse($this->form->setPrimaryField(new stdClass()), 'Fails non-fieldBuilder class');
		$this->assertFalse($this->form->setPrimaryField(fieldBuilder::createField('bar')), 'Fails non-added fieldBuilder');
	}

	function test_setPrimaryFields_resetExistingList(){
		$this->assertAttributeEquals(array(), 'primaryFields', $this->form, "It starts off as a blank list");

		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->form->setPrimaryField('foo'));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->form);

		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->form->setPrimaryField('bar'));
		$this->assertAttributeEquals(array('bar'), 'primaryFields', $this->form);
	}

	function test_setPrimaryFields_byName(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->form->setPrimaryField('foo'));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->form);

		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->form->setPrimaryField('foo', 'bar'));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->form);
	}

	function test_setPrimaryFields_byObject(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->form->setPrimaryField($foo));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->form);

		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->form->setPrimaryField($foo, $bar));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->form);
	}

	function test_setPrimaryFields_byNameAndObject(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->form->setPrimaryField('foo', $bar));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->form);
	}

	function test_listPrimaryFields(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->form->addField($cat = fieldBuilder::createField('cat'));
		$this->form->setPrimaryField('foo', 'cat');

		$priFields = $this->form->listPrimaryFields();
		$this->assertContains('foo', $priFields);
		$this->assertContains('cat', $priFields);
	}

	function test_getPrimaryFields(){
		$this->assertAttributeEquals(array(), 'primaryFields', $this->form, "It starts off as a blank list");
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->form->addField($cat = fieldBuilder::createField('cat'));
		$this->form->setPrimaryField('foo', 'cat');

		$priFields = $this->form->getPrimaryFields();
		$this->assertContains($foo, $priFields);
		$this->assertContains($cat, $priFields);
	}
}
 