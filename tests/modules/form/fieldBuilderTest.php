<?php

class fieldBuilderTest extends PHPUnit_Framework_TestCase{
	private static $dbDrive;

	static function setUpBeforeClass(){
		self::$dbDrive = db::getInstance()->create('mysql', array(
			'dsn'    => $GLOBALS['DB_DSN'],
			'user'   => $GLOBALS['DB_USER'],
			'pass'   => $GLOBALS['DB_PASSWD'],
			'dbname' => $GLOBALS['DB_DBNAME'],
		), 'appDB');
		// Reset database
		self::$dbDrive->query(file_get_contents(__DIR__ . '/fieldBuilderTest.sql'));

	}

	private function assertIsInputTag($testVar, $type){
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array(
				'type' => $type
			),
		), $testVar);
	}

	// ----------------------------------------------------

	function testItAcceptsAnArray(){
		$field = fieldBuilder::createField(array('name' => 'foo'));
		$this->assertInstanceOf('fieldBuilder', $field);
	}

	function testItAcceptsAString(){
		$field = fieldBuilder::createField('foo');
		$this->assertInstanceOf('fieldBuilder', $field);
	}

	function testDefaultValues(){
		$field = fieldBuilder::createField(array('name' => 'foo'));
		$this->assertFalse($field->disabled);
		$this->assertFalse($field->duplicates);
		$this->assertFalse($field->optional);
		$this->assertFalse($field->readonly);
		$this->assertFalse($field->dragDrop);
		$this->assertFalse($field->required);
		$this->assertFalse($field->disableStyling);
		$this->assertFalse($field->multiple);
		$this->assertNull($field->validate);
		$this->assertEmpty($field->linkedTo);
		$this->assertEmpty($field->help);
		$this->assertEmpty($field->value);
		$this->assertEmpty($field->placeholder);
		$this->assertEmpty($field->label);
		$this->assertEmpty($field->labelMetadata);
		$this->assertEmpty($field->fieldMetadata);
		$this->assertEmpty($field->fieldCSS);
		$this->assertEmpty($field->fieldClass);
		$this->assertEmpty($field->fieldID);
		$this->assertEmpty($field->labelCSS);
		$this->assertEmpty($field->labelClass);
		$this->assertEmpty($field->labelID);
		$this->assertEmpty($field->selectValues);
		$this->assertEquals('text', $field->type);
		$this->assertEquals(40, $field->size);
	}

	function testGetMethod(){
		$field = fieldBuilder::createField(array('name' => 'foo'));
		$this->assertEquals($field->name, 'foo');
	}

	function testSetMethod(){
		$field = fieldBuilder::createField(array('name' => 'foo'));
		$this->assertEquals($field->name, 'foo');
		$field->name = 'bar';
		$this->assertEquals($field->name, 'bar');
	}

	function testSetClearsRenderCache(){
		$field = fieldBuilder::createField(array('name' => 'foo'));
		$field->render();
		$this->assertAttributeNotEmpty('renderedHTML', $field);
		$field->name = 'bar';
		$this->assertAttributeEmpty('renderedHTML', $field);
	}

	function testSleep(){
		$field      = fieldBuilder::createField('foo');
		$serialized = serialize($field);
		$this->assertTrue(is_string($serialized));
		$field = unserialize($serialized);
		$this->assertNotEquals(FALSE, $field);
		$this->assertInstanceOf('fieldBuilder', $field);
	}

	function testToString(){
		$field = fieldBuilder::createField('foo');
		$this->assertTag(array(
			'tag' => 'input',
		), $field->render());
	}

	function testItFailsIfNotGivenAFieldName(){
		$field = fieldBuilder::createField(array());
		$this->assertFalse($field);
	}

	function testItAllowsUsToOverrideADefaultValue(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'size' => 10
		));
		$this->assertEquals(10, $field->size);
	}

	function testType_text(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'text'
		));
		$this->assertIsInputTag($field->render(), 'text');
	}

	function testType_button(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'button'
		));
		$this->assertIsInputTag($field->render(), 'button');
	}

	function testType_reset(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'reset'
		));
		$this->assertIsInputTag($field->render(), 'reset');
	}

	function testType_search(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'search'
		));
		$this->assertIsInputTag($field->render(), 'search');
	}

	function testType_tel(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'tel'
		));
		$this->assertIsInputTag($field->render(), 'tel');
	}

	function testType_color(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'color'
		));
		$this->assertIsInputTag($field->render(), 'color');
	}

	function testType_date(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'date'
		));
		$this->assertIsInputTag($field->render(), 'date');
	}

	function testType_datetime(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'datetime'
		));
		$this->assertIsInputTag($field->render(), 'datetime');
	}

	function testType_datetime_local(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'datetime-local'
		));
		$this->assertIsInputTag($field->render(), 'datetime-local');
	}

	function testType_email(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'email'
		));
		$this->assertIsInputTag($field->render(), 'email');
	}

	function testType_file(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'file'
		));
		$this->assertIsInputTag($field->render(), 'file');
	}

	function testType_hidden(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'hidden'
		));
		$this->assertIsInputTag($field->render(), 'hidden');
	}

	function testType_image(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'image'
		));
		$this->assertIsInputTag($field->render(), 'image');
	}

	function testType_month(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'month'
		));
		$this->assertIsInputTag($field->render(), 'month');
	}

	function testType_number(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'number'
		));
		$this->assertIsInputTag($field->render(), 'number');
	}

	function testType_password(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'password'
		));
		$this->assertIsInputTag($field->render(), 'password');
	}

	function testType_radio(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'radio'
		));
		$this->assertIsInputTag($field->render(), 'radio');
	}

	function testType_range(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'range'
		));
		$this->assertIsInputTag($field->render(), 'range');
	}

	function testType_submit(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'submit'
		));
		$this->assertIsInputTag($field->render(), 'submit');
	}

	function testType_time(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'time'
		));
		$this->assertIsInputTag($field->render(), 'time');
	}

	function testType_url(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'week'
		));
		$this->assertIsInputTag($field->render(), 'week');
	}

	function testType_week(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'week'
		));
		$this->assertIsInputTag($field->render(), 'week');
	}

	function testType_checkbox(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'checkbox'
		));
		$this->assertIsInputTag($field->render(), 'checkbox');
	}

	function testType_wysiwyg(){
	}

	function testFieldMetadata_basicAttributes(){
		$field = fieldBuilder::createField(array(
			'name'          => 'foo',
			'type'          => 'text',
			'fieldMetadata' => array(
				'a' => 1,
				'b' => 2,
				'c' => 3,
			),
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array(
				'a' => 1,
				'b' => 2,
				'c' => 3
			),
		), $field->render());
	}

	function testFieldMetadata_dataAttributes(){
		$field = fieldBuilder::createField(array(
			'name'          => 'foo',
			'type'          => 'text',
			'fieldMetadata' => array(
				'data' => array(
					'a' => 1,
					'b' => 2,
					'c' => 3,
				),
			),
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array(
				'data-a' => 1,
				'data-b' => 2,
				'data-c' => 3
			),
		), $field->render());
	}

	function testFieldMetadata_booleanConversion(){
		$field = fieldBuilder::createField(array(
			'name'          => 'foo',
			'type'          => 'text',
			'fieldMetadata' => array(
				'a' => TRUE,
				'b' => FALSE,
			),
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array(
				'a' => 1,
				'b' => 0,
			),
		), $field->render());
	}

	function testFieldAttributes_name(){
		$field = fieldBuilder::createField('foo');
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('name' => 'foo'),
		), $field->render());

		$field = fieldBuilder::createField('bar');
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('name' => 'bar'),
		), $field->render());
	}

	function testFieldAttributes_value(){
		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'value' => 123,
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('value' => '123'),
		), $field->render());
	}

	function testFieldAttributes_placeholder(){
		$field = fieldBuilder::createField(array(
			'name'        => 'foo',
			'placeholder' => 'Hello World'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('placeholder' => 'Hello World'),
		), $field->render());
	}

	function testFieldAttributes_fieldID(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'fieldID' => 'abc',
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('id' => 'abc'),
		), $field->render());
	}

	function testFieldAttributes_disabled(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'disabled' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\bdisabled\b/', $field));
	}

	function testFieldAttributes_readonly(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'readonly' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\breadonly\b/', $field));
	}

	function testFieldAttributes_required(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'required' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\brequired\b/', $field));
	}

	function testFieldAttributes_multiple(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'multiple' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\bmultiple\b/', $field));
	}

	function testFieldAttributes_disabledNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'disabled' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\bdisabled\b/', $field));
	}

	function testFieldAttributes_readonlyNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'readonly' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\breadonly\b/', $field));
	}

	function testFieldAttributes_requiredNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'required' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\brequired\b/', $field));
	}

	function testFieldAttributes_multipleNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'multiple' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\bmultiple\b/', $field->render()));
	}

	function testFieldAttributes_fieldCSS(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'fieldCSS' => 'color:red;'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('style' => 'color:red;'),
		), $field->render());

	}

	function testFieldAttributes_fieldCSSWhenDisableStylingIsTrue(){
		$field = fieldBuilder::createField(array(
			'name'           => 'foo',
			'disableStyling' => TRUE,
			'fieldCSS'       => 'color:red;'
		));
		$this->assertNotTag(array(
			'tag'        => 'input',
			'attributes' => array('style' => 'color:red;'),
		), $field->render());

	}

	function testFieldAttributes_fieldCSSWhenDisableStylingIsFalse(){
		$field = fieldBuilder::createField(array(
			'name'           => 'foo',
			'disableStyling' => FALSE,
			'fieldCSS'       => 'color:red;'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('style' => 'color:red;'),
		), $field->render());
	}

	function testFieldAttributes_fieldClass(){
		$field = fieldBuilder::createField(array(
			'name'       => 'foo',
			'fieldClass' => 'green'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('class' => 'green'),
		), $field->render());

	}

	function testFieldAttributes_fieldClassWhenDisableStylingIsTrue(){
		$field = fieldBuilder::createField(array(
			'name'           => 'foo',
			'disableStyling' => TRUE,
			'fieldClass'     => 'green'
		));
		$this->assertNotTag(array(
			'tag'        => 'input',
			'attributes' => array('class' => 'green'),
		), $field->render());

	}

	function testFieldAttributes_fieldClassWhenDisableStylingIsFalse(){
		$field = fieldBuilder::createField(array(
			'name'           => 'foo',
			'disableStyling' => FALSE,
			'fieldClass'     => 'green'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('class' => 'green'),
		), $field->render());
	}

	function testSelectOptions_passedArray(){
		$field = fieldBuilder::createField(array(
			'name'         => 'foo',
			'type'         => 'select',
			'selectValues' => array(
				'1' => 'a',
				'2' => 'b',
				'3' => 'c',
			),
		));

		$this->assertTag(array(
			'tag'      => 'select',
			'children' => array(
				'count' => 3,
				'only'  => array('tag' => 'option')
			),
		), $field->render());
	}

	function testSelectOptions_selectedValue(){
		$field = fieldBuilder::createField(array(
			'name'         => 'foo',
			'type'         => 'select',
			'value'        => 1,
			'selectValues' => array(
				'1' => 'a',
				'2' => 'b',
				'3' => 'c',
			),
		));

		$this->assertEquals('<select name="foo"><option value="1" selected>a</option><option value="2">b</option><option value="3">c</option></select>', $field->render());
	}

	function testSelectOptions_selectedMultipleValues(){
		$field = fieldBuilder::createField(array(
			'name'         => 'foo',
			'type'         => 'select',
			'value'        => array(1, 2),
			'selectValues' => array(
				'1' => 'a',
				'2' => 'b',
				'3' => 'c',
			),
		));

		$this->assertEquals('<select name="foo" multiple><option value="1" selected>a</option><option value="2" selected>b</option><option value="3">c</option></select>', $field->render());
	}

	function testSelectOptions_linkedToBase(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
			),
		));

		$this->assertTag(array(
			'tag'      => 'select',
			'children' => array(
				'count' => 6,
				'only'  => array('tag' => 'option')
			),
		), $field->render());
	}

	function testSelectOptions_linkedToBaseSelectedValue(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'value'    => 1,
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'limit' => 3
			),
		));

		$this->assertEquals('<select name="foo"><option value="1" selected>a</option><option value="2">b</option><option value="3">c</option></select>', $field->render());
	}

	function testSelectOptions_linkedToBaseSelectedMultipleValues(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'value'    => array(1, 2),
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'limit' => 3
			),
		));

		$this->assertEquals('<select name="foo" multiple><option value="1" selected>a</option><option value="2" selected>b</option><option value="3">c</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomWhere(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'where' => "name='a'"
			),
		));

		$this->assertEquals('<select name="foo"><option value="1">a</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomSort(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'order' => 'name DESC'
			),
		));

		$this->assertEquals('<select name="foo"><option value="4">z</option><option value="5">y</option><option value="6">x</option><option value="3">c</option><option value="2">b</option><option value="1">a</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'limit' => '2'
			),
		));

		$this->assertEquals('<select name="foo"><option value="1">a</option><option value="2">b</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomLimitRange(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'limit' => '2,1'
			),
		));

		$this->assertEquals('<select name="foo"><option value="3">c</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomWhereAndSort(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'where' => 'id>3',
				'order' => 'name DESC'
			),
		));

		$this->assertEquals('<select name="foo"><option value="4">z</option><option value="5">y</option><option value="6">x</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomWhereAndLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'where' => 'id>3',
				'limit' => '1'
			),
		));

		$this->assertEquals('<select name="foo"><option value="6">x</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomSortAndLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'order' => 'name DESC',
				'limit' => '1'
			),
		));

		$this->assertEquals('<select name="foo"><option value="4">z</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedCustomWhereSortLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'field' => 'name',
				'key'   => 'ID',
				'table' => 'fieldBuilderTest',
				'where' => 'id>3',
				'order' => 'name DESC',
				'limit' => '1'
			),
		));

		$this->assertEquals('<select name="foo"><option value="4">z</option></select>', $field->render());
	}

	function testSelectOptions_linkedToPassedRawSQL(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'sql' => "SELECT 'some','value'",
			),
		));

		$this->assertEquals('<select name="foo"><option value="some">value</option></select>', $field->render());

	}

	function testSelectOptions_linkedToPassedInvalidArray(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(),
		));

		$this->assertTag(array(
			'tag'     => 'select',
			'content' => ''
		), $field->render());
	}
}
 