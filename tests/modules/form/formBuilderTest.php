<?php


class formBuilderTest extends PHPUnit_Framework_TestCase{
	/**
	 * @var formBuilder
	 */
	private $form;

	function setUp(){
		$this->form = formBuilder::createForm();
	}

	static function setUpBeforeClass(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));
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
		$formOutput = $this->form->displayInsertForm(__DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_absoluteTemplatePathFile(){
		$formOutput = $this->form->displayInsertForm(__DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insert.html');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->displayInsertForm('test');
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
		$formOutput = $this->form->displayInsertForm('Test String');
		$this->assertEquals('Test String', $formOutput);
	}

	function test_templates_formTitle(){
		$template = '{formTitle}';

		$this->assertEquals('', $this->form->processTemplate($template));

		$form = formBuilder::createForm('foo');
		$this->assertEquals('foo', $form->processTemplate($template));
	}

	function test_templates_formBegin(){
		$this->assertEquals('<form method="post" >', $this->form->processTemplate('{form}'));
	}

	function test_templates_formBegin_hiddenTrue(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));
		$this->assertEquals('<form method="post" >'.$foo->render(), $this->form->processTemplate('{form hidden="true"}'));
	}
	function test_templates_formBegin_hiddenFalse(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));
		$this->assertEquals('<form method="post" >', $this->form->processTemplate('{form hidden="false"}'));
	}

	function test_templates_formBegin_miscAttributes(){
		$this->assertEquals('<form method="post" foo="bar" red="green">', $this->form->processTemplate('{form foo="bar" red="green"}'));
	}

	function test_templates_formEnd(){
		$this->assertEquals('</form>', $this->form->processTemplate('{/form}'));
	}

	function test_templates_fields_default(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->form->processTemplate('{fields}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_templates_fields_full(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->form->processTemplate('{fields display="full"}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_templates_fields_fields(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->form->processTemplate('{fields display="fields"}');
		$this->assertContains($foo->renderField(), $template, "The rendered template contains the foo field");
		$this->assertNotContains($foo->renderLabel(), $template, "The rendered template doesn't contain the foo label");
		$this->assertContains($bar->renderField(), $template, "The rendered template contains the bar field");
		$this->assertNotContains($bar->renderLabel(), $template, "The rendered template doesn't contain the bar label");
	}

	function test_templates_fields_labels(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->form->processTemplate('{fields display="labels"}');
		$this->assertContains($foo->renderLabel(), $template, "The rendered template contains the foo label");
		$this->assertNotContains($foo->renderField(), $template, "The rendered template doesn't contain the foo field");
		$this->assertContains($bar->renderLabel(), $template, "The rendered template contains the bar label");
		$this->assertNotContains($bar->renderField(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_templates_fields_hidden(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));

		$template = $this->form->processTemplate('{fields display="hidden"}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($cat->render(), $template, "The rendered template contains the cat field");
		$this->assertNotContains($bar->render(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_templates_fields_invalidOption(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertEquals('', $this->form->processTemplate('{fields display="INVALID"}'));
	}

	function test_templates_field_default(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->render(), $this->form->processTemplate('{field name="foo"}'));
	}

	function test_templates_field_full(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->render(), $this->form->processTemplate('{field name="foo" display="full"}'));
	}

	function test_templates_field_label(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->renderLabel(), $this->form->processTemplate('{field name="foo" display="label"}'));
	}

	function test_templates_field_field(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->renderField(), $this->form->processTemplate('{field name="foo" display="field"}'));
	}

	function test_templates_field_invalidFieldName(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals('', $this->form->processTemplate('{field name="invalid"}'));
	}

	function test_templates_field_invalidDisplay(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals('', $this->form->processTemplate('{field name="foo" display="invalid"}'));
	}

	function test_templates_fieldsetBeginNoLegend(){
		$this->assertEquals('<fieldset>', $this->form->processTemplate('{fieldset}'));
	}

	function test_templates_fieldsetBeginEmptyLegend(){
		$this->assertEquals('<fieldset>', $this->form->processTemplate('{fieldset legend=""}'));
	}

	function test_templates_fieldsetBeginLegend(){
		$this->assertEquals('<fieldset><legend>foo</legend>', $this->form->processTemplate('{fieldset legend="foo"}'));
	}

	function test_templates_fieldsetEnd(){
		$this->assertEquals('</fieldset>', $this->form->processTemplate('{/fieldset}'));
	}

	function test_templates_noneFormBuilderTag(){
		$this->assertEquals('{localvars var="foo"}', $this->form->processTemplate('{localvars var="foo"}'));
	}

	function test_templates_complexTemplate(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$template = '{fieldset legend="test"}{fieldset}{field name="foo"}{/fieldset}{/fieldset}';
		$expected = '<fieldset><legend>test</legend><fieldset>'.$foo->render().'</fieldset></fieldset>';
		$this->assertEquals($expected, $this->form->processTemplate($template));
	}

	function test_templates_fieldsLoop(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->form->processTemplate('{fieldsLoop}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_listedFields(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->form->addField($dog = fieldBuilder::createField('dog'));
		$this->form->addField($cat = fieldBuilder::createField('cat'));
		$template = $this->form->processTemplate('{fieldsLoop list="foo,dog"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$dog->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_null(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->form->processTemplate('{fieldsLoop editStrip="null"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_true(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->form->processTemplate('{fieldsLoop editStrip="true"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li>', $template);
		$template = $this->form->processTemplate('{fieldsLoop editStrip="1"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_false(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->form->processTemplate('{fieldsLoop editStrip="false"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
		$template = $this->form->processTemplate('{fieldsLoop editStrip="0"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_listedFieldsAndEditStrip(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => TRUE)));
		$this->form->addField($dog = fieldBuilder::createField(array('name' => 'dog', 'showInEditStrip' => FALSE)));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'showInEditStrip' => TRUE)));
		$template = $this->form->processTemplate('{fieldsLoop editStrip="true" list="foo,bar"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_showHiddenTrue(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type'=>'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type'=>'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type'=>'text')));
		$template = $this->form->processTemplate('{fieldsLoop showHidden="true"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
		$template = $this->form->processTemplate('{fieldsLoop showHidden="1"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_fieldsLoop_showHiddenFalse(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type'=>'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type'=>'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type'=>'text')));
		$template = $this->form->processTemplate('{fieldsLoop showHidden="false"}<li>{field}</li>{/fieldsLoop}');
		$this->assertNotContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
		$template = $this->form->processTemplate('{fieldsLoop showHidden="0"}<li>{field}</li>{/fieldsLoop}');
		$this->assertNotContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_fieldsLoop_showHiddenWithList(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type'=>'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type'=>'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type'=>'text')));
		$template = $this->form->processTemplate('{fieldsLoop showHidden="true" list="foo,cat"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_rowLoop(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Create a new form
		$form = formBuilder::createForm("rowLoop", array('table' => 'fieldBuilderTest', 'limit' => 3));

		// Add a field
		$form->addField($foo = fieldBuilder::createField(array('name' => 'name')));

		// Process the template
		$template = $form->processTemplate('{rowLoop}<li>{field display="field" name="name"}</li>{/rowLoop}');

		// Assertions
		$this->assertEquals('<li>'.$foo->renderField().'</li><li>'.$foo->renderField().'</li><li>'.$foo->renderField().'</li>', $template);
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
 