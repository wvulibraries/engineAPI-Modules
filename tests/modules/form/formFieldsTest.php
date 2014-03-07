<?php

class formFieldsTester extends formFields{}

class formFieldsTest extends PHPUnit_Framework_TestCase{
	/**
	 * @var formFields
	 */
	private $formFields;

	function setUp(){
		$this->formFields = new formFieldsTester;
	}

	// ============================================

	function test_itStartsOffWithNoFields(){
		$this->assertEmpty(sizeof($this->formFields));
	}

	function test_addFieldWithArray(){
		$this->assertCount(0, $this->formFields);
		$test = $this->formFields->addField(array('name' => 'foo'));
		$this->assertTrue($test);
		$this->assertCount(1, $this->formFields);
	}

	function test_addFieldWithString(){
		$this->assertCount(0, $this->formFields);
		$test = $this->formFields->addField('foo');
		$this->assertTrue($test);
		$this->assertCount(1, $this->formFields);
	}

	function test_addFieldWithFieldBuilder(){
		$this->assertCount(0, $this->formFields);
		$field = fieldBuilder::createField('foo');
		$test  = $this->formFields->addField($field);
		$this->assertTrue($test);
		$this->assertCount(1, $this->formFields);
	}

	function test_addFieldReturnsFalseOnError_noName(){
		$test = $this->formFields->addField(array());
		$this->assertFalse($test);
	}

	function test_addFieldReturnsFalseOnError_dupeNames(){
		$test = $this->formFields->addField(array('name' => 'foo'));
		$this->assertTrue($test);
		$test = $this->formFields->addField(array('name' => 'foo'));
		$this->assertFalse($test, 'It rejects duplicate field names');
	}

	function test_addFieldReturnsFalseOnError_dupeIDs(){
		$test = $this->formFields->addField(array(
			'name'    => 'foo',
			'fieldID' => 'test'));
		$this->assertTrue($test);
		$test = $this->formFields->addField(array(
			'name'    => 'bar',
			'fieldID' => 'test'
		));
		$this->assertFalse($test, 'It rejects duplicate field IDs');
	}

	function test_addFieldReturnsFalseOnError_dupeLabels(){
		$test = $this->formFields->addField(array(
			'name'  => 'foo',
			'label' => 'test'));
		$this->assertTrue($test);
		$test = $this->formFields->addField(array(
			'name'  => 'bar',
			'label' => 'test'
		));
		$this->assertFalse($test, 'It rejects duplicate field labels');
	}

	function test_removeField(){
		$test = $this->formFields->addField(array('name' => 'foo'));
		$this->assertTrue($test);

		$test = $this->formFields->removeField('foo');
		$this->assertTrue($test);
		$this->assertCount(0, $this->formFields);
	}

	function test_removeField_invalidFieldName(){
		$test = $this->formFields->addField(array('name' => 'foo'));
		$this->assertTrue($test);

		$test = $this->formFields->removeField('bar');
		$this->assertFalse($test);
		$this->assertCount(1, $this->formFields);
	}

	function test_modifyField_acceptsFieldbuilder(){
		$this->assertCount(0, $this->formFields);
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('', $field->label);
		$this->formFields->modifyField($field, 'label', 'foo');
		$this->assertEquals('foo', $field->label);
		$this->assertCount(0, $this->formFields);
	}

	function test_modifyField_existingField(){
		$this->assertCount(0, $this->formFields);
		$field = fieldBuilder::createField('foo');
		$this->formFields->addField($field);
		$this->assertCount(1, $this->formFields);
		$this->assertEquals('', $field->label);
		$this->formFields->modifyField('foo', 'label', 'bar');
		$this->assertEquals('bar', $field->label);
	}

	function test_modifyField_undefinedField(){
		$this->assertCount(0, $this->formFields);
		$test = $this->formFields->modifyField('foo', 'label', 'bar');
		$this->assertFalse($test);
	}

	function test_modifyAllFields(){
		$this->assertCount(0, $this->formFields);
		$fieldA = fieldBuilder::createField('foo');
		$fieldB = fieldBuilder::createField('bar');
		$this->formFields->addField($fieldA);
		$this->formFields->addField($fieldB);
		$this->assertCount(2, $this->formFields);
		$this->assertEquals('', $fieldA->label);
		$this->assertEquals('', $fieldB->label);
		$this->formFields->modifyAllFields('label', 'test');
		$this->assertEquals('test', $fieldA->label);
		$this->assertEquals('test', $fieldB->label);
	}

	function test_fieldExists_existingField(){
		$this->formFields->addField(fieldBuilder::createField('foo'));
		$this->assertTrue($this->formFields->fieldExists('foo'));
	}

	function test_fieldExists_undefinedField(){
		$this->assertFalse($this->formFields->fieldExists('foo'));
	}

	function test_getField_existingField(){
		$field = fieldBuilder::createField('foo');
		$this->formFields->addField($field);
		$test = $this->formFields->getField('foo');
		$this->assertEquals($field, $test);
	}

	function test_getField_undefinedField(){
		$this->assertNull($this->formFields->getField('foo'));
	}

	function test_listFields(){
		$fieldA = fieldBuilder::createField('foo');
		$fieldB = fieldBuilder::createField('bar');
		$this->formFields->addField($fieldA);
		$this->formFields->addField($fieldB);

		$test = $this->formFields->listFields();
		$this->assertTrue(is_array($test));
		$this->assertCount(2, $test);
		$this->assertContains('foo', $test);
		$this->assertContains('bar', $test);
	}

	function test_listFields_noExistingFields(){
		$test = $this->formFields->listFields();
		$this->assertTrue(is_array($test));
		$this->assertCount(0, $test);
	}

	function test_fieldOrdering(){
		$this->formFields->addField(array(
			'name'  => '4',
			'order' => 3
		));
		$this->formFields->addField(array(
			'name'  => '2',
			'order' => 2
		));
		$this->formFields->addField(array(
			'name' => '5'
		));
		$this->formFields->addField(array(
			'name'  => '3',
			'order' => 2
		));
		$this->formFields->addField(array(
			'name'  => '1',
			'order' => 1
		));

		$this->assertEquals(array('1', '2', '3', '4', '5'), $this->formFields->listFields());
	}

	function test_setPrimaryFields_errorCases(){
		$this->assertFalse($this->formFields->setPrimaryField('foo'), 'Fails undefined field');
		$this->assertFalse($this->formFields->setPrimaryField(new stdClass()), 'Fails non-fieldBuilder class');
		$this->assertFalse($this->formFields->setPrimaryField(fieldBuilder::createField('bar')), 'Fails non-added fieldBuilder');
	}

	function test_setPrimaryFields_resetExistingList(){
		$this->assertAttributeEquals(array(), 'primaryFields', $this->formFields, "It starts off as a blank list");

		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->formFields->setPrimaryField('foo'));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->formFields);

		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->formFields->setPrimaryField('bar'));
		$this->assertAttributeEquals(array('bar'), 'primaryFields', $this->formFields);
	}

	function test_setPrimaryFields_byName(){
		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->formFields->setPrimaryField('foo'));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->formFields);

		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->formFields->setPrimaryField('foo', 'bar'));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->formFields);
	}

	function test_setPrimaryFields_byObject(){
		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->assertTrue($this->formFields->setPrimaryField($foo));
		$this->assertAttributeEquals(array('foo'), 'primaryFields', $this->formFields);

		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->formFields->setPrimaryField($foo, $bar));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->formFields);
	}

	function test_setPrimaryFields_byNameAndObject(){
		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->assertTrue($this->formFields->setPrimaryField('foo', $bar));
		$this->assertAttributeEquals(array('foo', 'bar'), 'primaryFields', $this->formFields);
	}

	function test_listPrimaryFields(){
		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->formFields->addField($cat = fieldBuilder::createField('cat'));
		$this->formFields->setPrimaryField('foo', 'cat');

		$priFields = $this->formFields->listPrimaryFields();
		$this->assertContains('foo', $priFields);
		$this->assertContains('cat', $priFields);
	}

	function test_getPrimaryFields(){
		$this->assertAttributeEquals(array(), 'primaryFields', $this->formFields, "It starts off as a blank list");
		$this->formFields->addField($foo = fieldBuilder::createField('foo'));
		$this->formFields->addField($bar = fieldBuilder::createField('bar'));
		$this->formFields->addField($cat = fieldBuilder::createField('cat'));
		$this->formFields->setPrimaryField('foo', 'cat');

		$priFields = $this->formFields->getPrimaryFields();
		$this->assertContains($foo, $priFields);
		$this->assertContains($cat, $priFields);
	}
}
 