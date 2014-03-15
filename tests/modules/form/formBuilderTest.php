<?php


class formBuilderTest extends PHPUnit_Framework_TestCase{
	/**
	 * @var formBuilder
	 */
	private $form;

	function setUp(){
		$this->form = formBuilder::createForm();
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);
	}

	private function assertFormData($formType){
		$validFormTypes = array('insertForm', 'updateForm', 'editTable', 'expandableEditTable');

		// Get the save form data from the session
		$formData = session::get(formBuilder::SESSION_SAVED_FORMS_KEY);
		$this->assertCount(1, $formData, 'Assert the savedForm array has 1 element');

		// Get the saved form's data
		$formData = array_pop($formData);

		// General asserts
		$this->assertTrue(is_array($formData), 'Assert the savedForm data is an array');
		$this->assertArrayHasKey('formBuilder', $formData, "formData contains 'formData' element");
		$this->assertInstanceOf('formBuilder', unserialize($formData['formBuilder']), "formData contains valid formBuilder object");
		$this->assertArrayHasKey('formType', $formData, "formData contains 'formType' element");
		$this->assertTrue(in_array($formData['formType'], $validFormTypes), "formData contains valid formType");

		// Specific asserts
		switch ($formType) {
			case 'insertForm':
				// Nothing more needed
				break;
			case 'updateForm':
				// TODO: test primary keys
				break;
			case 'editTable':
				// Nothing more needed
				break;
		}
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

	// absoluteTemplatePathDir
	function test_display_insert_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');

		$formOutput = $this->form->display('insert', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');

		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
	}

	// absoluteTemplatePathFile
	function test_display_insert_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insertUpdate.html');

		$formOutput = $this->form->display('insert', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insertUpdate.html');

		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/editTable.html');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/editTable.html');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
	}

	// distributionTemplatePath
	function test_display_insert_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');

		$formOutput = $this->form->display('insert', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_distributionTemplatePath(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form              = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));
		$form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options           = array('template' => 'test');

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm', $options);
		$this->assertEquals('Test Insert Form', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable', $options);
		$this->assertEquals('Test Edit Table', $formOutput);
		$this->assertFormData('expandableEditTable');
	}

	// noParameters
	function test_display_insert_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$formOutput = $this->form->display('insert');
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm');
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_noParameters(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form              = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));
		$form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update');
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm');
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable');
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
	}

	// nullTemplate
	function test_display_insert_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$formOutput = $this->form->display('insert', NULL);
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm', NULL);
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_nullTemplate(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form              = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));
		$form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update', NULL);
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm', NULL);
		$this->assertEquals('Insert Form Template', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable', NULL);
		$this->assertEquals('Edit Table Form', $formOutput);
		$this->assertFormData('expandableEditTable');
	}

	// templateBlob
	function test_display_insert_templateBlob(){
		$options    = array('template' => 'Test String');

		$formOutput = $this->form->display('insert', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('insertForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('insertForm', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('insertForm');
	}

	function test_display_update_templateBlob(){
		$options    = array('template' => 'Test String');

		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Success cases
		$form = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));

		$form->addField(fieldBuilder::createField(array(
			'name'    => 'ID',
			'value'   => 1,
			'primary' => TRUE,
			)
		));

		$formOutput = $form->display('update', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('updateForm');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $form->display('updateForm', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('updateForm');
	}

	function test_display_edit_templateBlob(){
		$options    = array('template' => 'Test String');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('edit', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('editTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('editTable', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('editTable');
	}

	function test_display_expandable_templateBlob(){
		$options    = array('template' => 'Test String');

		$this->form->addField(fieldBuilder::createField('foo'));
		$this->form->addPrimaryFields('foo');

		$formOutput = $this->form->display('expandable', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEdit', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableTable', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('expandableEditTable');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);

		$formOutput = $this->form->display('expandableEditTable', $options);
		$this->assertEquals('Test String', $formOutput);
		$this->assertFormData('expandableEditTable');
	}


	function test_display_assets() {
		$output = $this->form->display('assets');
		$this->assertStringStartsWith('<!-- engine Instruction displayTemplateOff -->', $output);
		$this->assertStringEndsWith("<!-- engine Instruction displayTemplateOn -->\n", $output);
	}

	function test_display_errors() {
		$this->assertEquals(errorHandle::prettyPrint(), $this->form->display('errors'));
	}

	function test_display_update_errorCases_noDatabase() {
		$form = formBuilder::createForm("updateForm");
		$form->addField(fieldBuilder::createField(array(
				'name'    => 'ID',
				'value'   => 1,
				'primary' => TRUE,
			)
		));

		$formOutput = $form->display('updateForm');
		$this->assertEquals('Misconfigured formBuilder!', $formOutput);
	}
	function test_display_update_errorCases_noREcord() {
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Setup formBuilder
		$form = formBuilder::createForm("updateForm", array('table' => 'fieldBuilderTest'));
		$form->addField(fieldBuilder::createField(array(
				'name'    => 'ID',
				'primary' => TRUE,
			)
		));

		$formOutput = $form->display('updateForm');
		$this->assertEquals('Misconfigured formBuilder!', $formOutput);

		$form->modifyField('ID', 'value', 9999);
		$formOutput = $form->display('updateForm');
		$this->assertEquals('No record found!', $formOutput);
	}

	function test_display_invalidType() {
		$this->assertEquals('', $this->form->display('somethingInvalid'));
	}

	function test_linkToDatabase() {
		$this->assertTrue($this->form->linkToDatabase(array('table' => 'fieldBuilderTest')));
	}

	function test_linkToDatabase_noTable() {
		$this->assertFalse($this->form->linkToDatabase(array()));
	}

	// Old display tests - keeping for now to remember each step and what it should output
	function test_displayInsertForm_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insertUpdate.html');
		$formOutput = $this->form->display('insertForm', $options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');
		$formOutput              = $this->form->display('insertForm', $options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->display('insertForm');
		$this->assertFormData('insertForm');
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->display('insertForm', NULL);
		$this->assertFormData('insertForm');
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_templateBlob(){
		$options    = array('template' => 'Test String');
		$formOutput = $this->form->display('insertForm', $options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test String', $formOutput);
	}
}
