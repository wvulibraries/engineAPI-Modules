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
		$validFormTypes = array('insertForm', 'updateForm', 'editTable');

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

	function test_displayInsertForm_absoluteTemplatePathDir(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_absoluteTemplatePathFile(){
		$options    = array('template' => __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR.'test/insert.html');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_distributionTemplatePath(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$options                 = array('template' => 'test');
		$formOutput              = $this->form->displayInsertForm($options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test Insert Form', $formOutput);
	}

	function test_displayInsertForm_noParameters(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->displayInsertForm();
		$this->assertFormData('insertForm');
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_nullTemplate(){
		$this->form->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$formOutput              = $this->form->displayInsertForm(NULL);
		$this->assertFormData('insertForm');
		$this->assertEquals('Insert Form Template', $formOutput);
	}

	function test_displayInsertForm_templateBlob(){
		$options    = array('template' => 'Test String');
		$formOutput = $this->form->displayInsertForm($options);
		$this->assertFormData('insertForm');
		$this->assertEquals('Test String', $formOutput);
	}

}
 