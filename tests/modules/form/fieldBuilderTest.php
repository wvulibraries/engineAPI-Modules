<?php

class fieldBuilderTest extends PHPUnit_Framework_TestCase{
	private static $dbDrive;

	static function setUpBeforeClass(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));
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
		$this->assertTrue($field->duplicates);
		$this->assertFalse($field->readonly);
		$this->assertFalse($field->required);
		$this->assertFalse($field->disableStyling);
		$this->assertFalse($field->multiple);
		$this->assertNull($field->validate);
		$this->assertEmpty($field->linkedTo);
		$this->assertEmpty($field->help);
		$this->assertEmpty($field->value);
		$this->assertEmpty($field->placeholder);
		$this->assertEquals($field->name, $field->label);
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
	}

	function testItAcceptsIntegerOrdering(){
		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'order' => '1',
		));
		$this->assertInstanceOf('fieldBuilder', $field);

		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'order' => 1,
		));
		$this->assertInstanceOf('fieldBuilder', $field);
	}

	function testItRejectsNonIntegerOrdering(){
		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'order' => 'a',
		));
		$this->assertFalse($field);

		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'order' => '123abc',
		));
		$this->assertFalse($field);

		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'order' => array(),
		));
		$this->assertFalse($field);
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
		), $field);
	}

	function testToSqlSnippet(){
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('`foo`=?', $field->toSqlSnippet());
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

	function test_render(){
		$field     = fieldBuilder::createField('foo');
		$testField = $field->renderField();
		$testLabel = $field->renderLabel();
		$this->assertEquals($testLabel.$testField, $field->render());
	}

	function test_renderLabelBasic(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'fieldID' => 'testFoo'
		));
		$this->assertRegExp('|<label.+?for="testFoo".*?>foo</label>|', $field->renderLabel());
	}

	function test_renderLabelCustomLabel(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'label'   => 'Testing',
			'fieldID' => 'testFoo'
		));
		$this->assertRegExp('|<label.+?for="testFoo".*?>Testing</label>|', $field->renderLabel());
	}

	function test_renderLabelRandFieldID(){
		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'label' => 'Testing',
		));
		$this->assertRegExp('|<label.+?for="[a-z0-9]{13}".*?>Testing</label>|', $field->renderLabel());
	}

	function testType_text(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'text'
		));
		$this->assertIsInputTag($field->renderField(), 'text');
	}

	function testType_button(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'button'
		));
		$this->assertIsInputTag($field->renderField(), 'button');
	}

	function testType_reset(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'reset'
		));
		$this->assertIsInputTag($field->renderField(), 'reset');
	}

	function testType_search(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'search'
		));
		$this->assertIsInputTag($field->renderField(), 'search');
	}

	function testType_tel(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'tel'
		));
		$this->assertIsInputTag($field->renderField(), 'tel');
	}

	function testType_color(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'color'
		));
		$this->assertIsInputTag($field->renderField(), 'color');
	}

	function testType_date(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'date'
		));
		$this->assertIsInputTag($field->renderField(), 'date');
	}

	function testType_datetime(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'datetime'
		));
		$this->assertIsInputTag($field->renderField(), 'datetime');
	}

	function testType_datetime_local(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'datetime-local'
		));
		$this->assertIsInputTag($field->renderField(), 'datetime-local');
	}

	function testType_email(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'email'
		));
		$this->assertIsInputTag($field->renderField(), 'email');
	}

	function testType_file(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'file'
		));
		$this->assertIsInputTag($field->renderField(), 'file');
	}

	function testType_hidden(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'hidden'
		));
		$this->assertIsInputTag($field->renderField(), 'hidden');
	}

	function testType_image(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'image'
		));
		$this->assertIsInputTag($field->renderField(), 'image');
	}

	function testType_month(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'month'
		));
		$this->assertIsInputTag($field->renderField(), 'month');
	}

	function testType_number(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'number'
		));
		$this->assertIsInputTag($field->renderField(), 'number');
	}

	function testType_password(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'password'
		));
		$renderedField = $field->renderField();
		$this->assertIsInputTag($renderedField, 'password');
		$this->assertSelectCount('input', 2, $renderedField, "There are exactly 2 password fields rendered");
	}

	function testType_range(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'range'
		));
		$this->assertIsInputTag($field->renderField(), 'range');
	}

	function testType_submit(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'submit'
		));
		$this->assertIsInputTag($field->renderField(), 'submit');
	}

	function testType_time(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'time'
		));
		$this->assertIsInputTag($field->renderField(), 'time');
	}

	function testType_url(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'week'
		));
		$this->assertIsInputTag($field->renderField(), 'week');
	}

	function testType_week(){
		$field = fieldBuilder::createField(array(
			'name' => 'foo',
			'type' => 'week'
		));
		$this->assertIsInputTag($field->renderField(), 'week');
	}

	function testType_radio(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'radio',
			'options' => array('a', 'b', 'c')
		));
		$this->assertIsInputTag($field->renderField(), 'radio');
	}

	function testType_radio_noOptions(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'radio',
		));
		$this->assertEquals('No options available', $field->renderField());
	}

	function testType_checkbox(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'checkbox',
			'options' => array('a', 'b', 'c')
		));
		$this->assertIsInputTag($field->renderField(), 'checkbox');
	}

	function testType_checkbox_singleOption(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'checkbox',
			'options' => array('a')
		));
		$this->assertIsInputTag($field->renderField(), 'checkbox');
	}

	function testType_checkbox_noOptions(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'checkbox',
		));
		$this->assertEquals('No options available', $field->renderField());
	}

	function testType_plaintext(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'plaintext',
			'value'   => '<b>Hello World!</b>',
		));
		$this->assertEquals('<b>Hello World!</b>', $field->renderField());
	}

	function testType_textarea(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'textarea',
		));
		$this->assertTag(array('tag'=>'textarea'), $field->renderField());
	}

	function testType_boolean_noLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'Yes',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Has a 'Yes' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'No',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Has a 'No' option");
	}

	function testType_boolean_withLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'labels' => array('False', 'True'),
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'True',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Has a 'True' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'False',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Has a 'False' option");
	}

	function testType_boolean_select_noLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'select',
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'Yes',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Select has a 'Yes' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'No',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Select has a 'No' option");
	}

	function testType_boolean_select_withLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'select',
				'labels' => array('False', 'True'),
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'True',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Select has a 'True' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'False',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Select has a 'False' option");
	}

	function testType_boolean_select_noLabels_includeEmpty(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'select',
				'blankOption' => 'Foo Option',
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'Yes',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Select has a 'Yes' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'No',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Select has a 'No' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'Foo Option',
			'attributes' => array(
				'value' => 'NULL',
			),
		), $renderedField, "Select has a 'Blank' option");
	}

	function testType_boolean_select_withLabels_includeEmpty(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'select',
				'blankOption' => TRUE,
				'labels' => array('False', 'True'),
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'True',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField, "Select has a 'True' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => 'False',
			'attributes' => array(
				'value' => '0',
			),
		), $renderedField, "Select has a 'False' option");

		$this->assertTag(array(
			'tag' => 'option',
			'content' => '',
			'attributes' => array(
				'value' => 'NULL',
			),
		), $renderedField, "Select has a 'Blank' option");
	}

	function testType_boolean_radio_noLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'radio',
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'label',
			'content' => 'Yes',
			'children' => array(
				'count' => 1,
				'only' => array(
					'tag' => 'input',
					'attributes' => array(
						'value' => '1',
					),
				),
			),
		), $renderedField, "Radio has a 'Yes' option");

		$this->assertTag(array(
			'tag' => 'label',
			'content' => 'No',
			'children' => array(
				'count' => 1,
				'only' => array(
					'tag' => 'input',
					'attributes' => array(
						'value' => '0',
					),
				),
			),
		), $renderedField, "Radio has a 'No' option");
	}

	function testType_boolean_radio_withLabels(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'radio',
				'labels' => array('False', 'True'),
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'label',
			'content' => 'True',
			'children' => array(
				'count' => 1,
				'only' => array(
					'tag' => 'input',
					'attributes' => array(
						'value' => '1',
					),
				),
			),
		), $renderedField, "Radio has a 'True' option");

		$this->assertTag(array(
			'tag' => 'label',
			'content' => 'False',
			'children' => array(
				'count' => 1,
				'only' => array(
					'tag' => 'input',
					'attributes' => array(
						'value' => '0',
					),
				),
			),
		), $renderedField, "Radio has a 'False' option");
	}

	function testType_boolean_checkbox(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'boolean',
			'options' => array(
				'type' => 'checkbox',
			)
		));

		$renderedField = $field->renderField();

		$this->assertTag(array(
			'tag' => 'input',
			'attributes' => array(
				'value' => '1',
			),
		), $renderedField);
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
		), $field->renderField());
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
		), $field->renderField());
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
		), $field->renderField());
	}

	function testFieldAttributes_name(){
		$field = fieldBuilder::createField('foo');
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('name' => 'foo'),
		), $field->renderField());

		$field = fieldBuilder::createField('bar');
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('name' => 'bar'),
		), $field->renderField());
	}

	function testFieldAttributes_value(){
		$field = fieldBuilder::createField(array(
			'name'  => 'foo',
			'value' => 123,
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('value' => '123'),
		), $field->renderField());
	}

	function testFieldAttributes_placeholder(){
		$field = fieldBuilder::createField(array(
			'name'        => 'foo',
			'placeholder' => 'Hello World'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('placeholder' => 'Hello World'),
		), $field->renderField());
	}

	function testFieldAttributes_fieldID(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'fieldID' => 'abc',
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('id' => 'abc'),
		), $field->renderField());
	}

	function testFieldAttributes_disabled(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'disabled' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\bdisabled\b/', $field->renderField()));
	}

	function testFieldAttributes_readonly(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'readonly' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\breadonly\b/', $field->renderField()));
	}

	function testFieldAttributes_required(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'required' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\brequired\b/', $field->renderField()));
	}

	function testFieldAttributes_multiple(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'multiple' => TRUE,
		));
		$this->assertEquals(1, preg_match('/\bmultiple\b/', $field->renderField()));
	}

	function testFieldAttributes_disabledNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'disabled' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\bdisabled\b/', $field->renderField()));
	}

	function testFieldAttributes_readonlyNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'readonly' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\breadonly\b/', $field->renderField()));
	}

	function testFieldAttributes_requiredNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'required' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\brequired\b/', $field->renderField()));
	}

	function testFieldAttributes_multipleNegative(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'multiple' => FALSE,
		));
		$this->assertNotEquals(1, preg_match('/\bmultiple\b/', $field->renderField()));
	}

	function testFieldAttributes_fieldCSS(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'fieldCSS' => 'color:red;'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('style' => 'color:red;'),
		), $field->renderField());

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
		), $field->renderField());

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
		), $field->renderField());
	}

	function testFieldAttributes_fieldClass(){
		$field = fieldBuilder::createField(array(
			'name'       => 'foo',
			'fieldClass' => 'green'
		));
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array('class' => 'green'),
		), $field->renderField());

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
		), $field->renderField());

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
		), $field->renderField());
	}

	function testSelectOptions_passedArray(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'select',
			'options' => array(
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
		), $field->renderField());
	}

	function testSelectOptions_selectedValue(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'select',
			'value'   => 1,
			'options' => array(
				'1' => 'a',
				'2' => 'b',
				'3' => 'c',
			),
		));

		$this->assertRegExp('|<select .+?><option value="1" selected>a</option><option value="2">b</option><option value="3">c</option></select>|', $field->renderField());
	}

	function testSelectOptions_selectedMultipleValues(){
		$field = fieldBuilder::createField(array(
			'name'    => 'foo',
			'type'    => 'select',
			'value'   => array(1, 2),
			'options' => array(
				'1' => 'a',
				'2' => 'b',
				'3' => 'c',
			),
		));

		$this->assertRegExp('|<select .+?multiple.*?><option value="1" selected>a</option><option value="2" selected>b</option><option value="3">c</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToBase(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
			),
		));

		$this->assertTag(array(
			'tag'      => 'select',
			'children' => array(
				'count' => 6,
				'only'  => array('tag' => 'option')
			),
		), $field->renderField());
	}

	function testSelectOptions_linkedToBaseSelectedValue(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'value'    => 1,
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignLimit' => 3
			),
		));

		$this->assertRegExp('|<select .+?><option value="1" selected>a</option><option value="2">b</option><option value="3">c</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToBaseSelectedMultipleValues(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'value'    => array(1, 2),
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignLimit' => 3
			),
		));

		$this->assertRegExp('|<select .+?multiple.*?><option value="1" selected>a</option><option value="2" selected>b</option><option value="3">c</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomWhere(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignWhere' => "name='a'"
			),
		));

		$this->assertRegExp('|<select .+?><option value="1">a</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomSort(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignOrder' => 'name DESC'
			),
		));

		$this->assertRegExp('|<select .+?><option value="4">z</option><option value="5">y</option><option value="6">x</option><option value="3">c</option><option value="2">b</option><option value="1">a</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignLimit' => '2'
			),
		));

		$this->assertRegExp('|<select .+?><option value="1">a</option><option value="2">b</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomLimitRange(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignLimit' => '2,1'
			),
		));

		$this->assertRegExp('|<select .+?><option value="3">c</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomWhereAndSort(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignWhere' => 'id>3',
				'foreignOrder' => 'name DESC'
			),
		));

		$this->assertRegExp('|<select .+?><option value="4">z</option><option value="5">y</option><option value="6">x</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomWhereAndLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignWhere' => 'id>3',
				'foreignLimit' => '1'
			),
		));

		$this->assertRegExp('|<select .+?><option value="6">x</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomSortAndLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignOrder' => 'name DESC',
				'foreignLimit' => '1'
			),
		));

		$this->assertRegExp('|<select .+?><option value="4">z</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedCustomWhereSortLimit(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignTable' => 'fieldBuilderTest',
				'foreignKey'   => 'ID',
				'foreignLabel' => 'name',
				'foreignWhere' => 'id>3',
				'foreignOrder' => 'name DESC',
				'foreignLimit' => '1'
			),
		));

		$this->assertRegExp('|<select .+?><option value="4">z</option></select>|', $field->renderField());
	}

	function testSelectOptions_linkedToPassedRawSQL(){
		$field = fieldBuilder::createField(array(
			'name'     => 'foo',
			'type'     => 'select',
			'linkedTo' => array(
				'foreignSQL' => "SELECT 'some','value'",
			),
		));

		$this->assertRegExp('|<select .+?><option value="some">value</option></select>|', $field->renderField());
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
		), $field->renderField());
	}

	function test_render_absoluteTemplatePathDir(){
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('Test Input Template', $field->render(__DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR.'test'));
	}

	function test_render_absoluteTemplatePathFile(){
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('Test Input Template', $field->render(__DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR.'test/input.html'));
	}

	function test_render_distributionTemplatePath(){
		$field              = fieldBuilder::createField('foo');
		$field->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
		$this->assertEquals('Test Input Template', $field->render('test'));
	}

	function test_render_noParameters(){
		$field              = fieldBuilder::createField('foo');
		$field->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
		$this->assertEquals($field->renderLabel().$field->renderField(), $field->render());
	}

	function test_render_nullTemplate(){
		$field              = fieldBuilder::createField('foo');
		$field->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
		$this->assertEquals($field->renderLabel().$field->renderField(), $field->render(NULL));
	}

	function test_render_templateBlob(){
		$field = fieldBuilder::createField('foo');
		$this->assertEquals('Test String', $field->render('Test String'));
	}
}
