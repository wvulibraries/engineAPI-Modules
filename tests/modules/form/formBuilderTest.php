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
		$this->assertAttributeCount(0, 'fields', $this->form);
		$this->assertCount(0, $this->form);
	}

	function test_itCountsTheNumberOfFields_withFields(){
		$this->assertAttributeCount(0, 'fields', $this->form);
		$this->assertCount(0, $this->form);

		$this->form->addField(array('name' => 'foo'));
		$this->assertAttributeCount(1, 'fields', $this->form);
		$this->assertCount(1, $this->form);

		$this->form->addField(array('name' => 'bar'));
		$this->assertAttributeCount(2, 'fields', $this->form);
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

	function test_addFieldWithArray(){
		$this->assertCount(0, $this->form);
		$test = $this->form->addField(array('name' => 'foo'));
		$this->assertTrue($test);
		$this->assertCount(1, $this->form);
	}

	function test_addFieldWithFieldBuilder(){
		$this->assertCount(0, $this->form);
		$field = fieldBuilder::createField('foo');
		$test  = $this->form->addField($field);
		$this->assertTrue($test);
		$this->assertCount(1, $this->form);
	}

	function test_addFieldReturnsFalseOnError_noName(){
		$test = $this->form->addField(array());
		$this->assertFalse($test);
	}

	function test_addFieldReturnsFalseOnError_notArrayOrFieldbuilder(){
		$test = $this->form->addField('foo');
		$this->assertFalse($test);
	}

	function test_addFieldReturnsFalseOnError_dupeNames(){
		$test = $this->form->addField(array('name' => 'foo'));
		$this->assertTrue($test);
		$test = $this->form->addField(array('name' => 'foo'));
		$this->assertFalse($test, 'It rejects duplicate field names');
	}

	function test_addFieldReturnsFalseOnError_dupeIDs(){
		$test = $this->form->addField(array(
			'name'    => 'foo',
			'fieldID' => 'test'));
		$this->assertTrue($test);
		$test = $this->form->addField(array(
			'name'    => 'bar',
			'fieldID' => 'test'
		));
		$this->assertFalse($test, 'It rejects duplicate field IDs');
	}

	function test_addFieldReturnsFalseOnError_dupeLabels(){
		$test = $this->form->addField(array(
			'name'  => 'foo',
			'label' => 'test'));
		$this->assertTrue($test);
		$test = $this->form->addField(array(
			'name'  => 'bar',
			'label' => 'test'
		));
		$this->assertFalse($test, 'It rejects duplicate field labels');
	}

	function test_removeField(){
		$test = $this->form->addField(array('name' => 'foo'));
		$this->assertTrue($test);

		$test = $this->form->removeField('foo');
		$this->assertTrue($test);
		$this->assertCount(0, $this->form);
	}

	function test_removeField_invalidFieldName(){
		$test = $this->form->addField(array('name' => 'foo'));
		$this->assertTrue($test);

		$test = $this->form->removeField('bar');
		$this->assertFalse($test);
		$this->assertCount(1, $this->form);
	}

	function test_modifyField_acceptsFieldbuilder(){
		$this->assertCount(0, $this->form);
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('', $field->label);
		$this->form->modifyField($field, 'label', 'foo');
		$this->assertEquals('foo', $field->label);
		$this->assertCount(0, $this->form);
	}

	function test_modifyField_existingField(){
		$this->assertCount(0, $this->form);
		$field = fieldBuilder::createField('foo');
		$this->form->addField($field);
		$this->assertCount(1, $this->form);
		$this->assertEquals('', $field->label);
		$this->form->modifyField('foo', 'label', 'bar');
		$this->assertEquals('bar', $field->label);
	}

	function test_modifyField_undefinedField(){
		$this->assertCount(0, $this->form);
		$test = $this->form->modifyField('foo', 'label', 'bar');
		$this->assertFalse($test);
	}

	function test_modifyAllFields(){
		$this->assertCount(0, $this->form);
		$fieldA = fieldBuilder::createField('foo');
		$fieldB = fieldBuilder::createField('bar');
		$this->form->addField($fieldA);
		$this->form->addField($fieldB);
		$this->assertCount(2, $this->form);
		$this->assertEquals('', $fieldA->label);
		$this->assertEquals('', $fieldB->label);
		$this->form->modifyAllFields('label', 'test');
		$this->assertEquals('test', $fieldA->label);
		$this->assertEquals('test', $fieldB->label);
	}

	function test_fieldExists_existingField(){
		$this->form->addField(fieldBuilder::createField('foo'));
		$this->assertTrue($this->form->fieldExists('foo'));
	}

	function test_fieldExists_undefinedField(){
		$this->assertFalse($this->form->fieldExists('foo'));
	}

	function test_getField_existingField(){
		$field = fieldBuilder::createField('foo');
		$this->form->addField($field);
		$test = $this->form->getField('foo');
		$this->assertEquals($field, $test);
	}

	function test_getField_undefinedField(){
		$this->assertNull($this->form->getField('foo'));
	}

	function test_listFields(){
		$fieldA = fieldBuilder::createField('foo');
		$fieldB = fieldBuilder::createField('bar');
		$this->form->addField($fieldA);
		$this->form->addField($fieldB);

		$test = $this->form->listFields();
		$this->assertTrue(is_array($test));
		$this->assertCount(2, $test);
		$this->assertContains('foo', $test);
		$this->assertContains('bar', $test);
	}

	function test_listFields_noExistingFields(){
		$test = $this->form->listFields();
		$this->assertTrue(is_array($test));
		$this->assertCount(0, $test);
	}

	function test_fieldOrdering(){
		$this->form->addField(array(
			'name'  => '4',
			'order' => 3
		));
		$this->form->addField(array(
			'name'  => '2',
			'order' => 2
		));
		$this->form->addField(array(
			'name' => '5'
		));
		$this->form->addField(array(
			'name'  => '3',
			'order' => 2
		));
		$this->form->addField(array(
			'name'  => '1',
			'order' => 1
		));

		$this->assertEquals(array('1', '2', '3', '4', '5'), $this->form->listFields());
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
 