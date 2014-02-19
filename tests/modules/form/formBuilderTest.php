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

}
 