<?php
class formBuilderTemplateTest extends PHPUnit_Framework_TestCase{
	/**
	 * @var formBuilder
	 */
	private $form;
	/**
	 * @var formBuilderTemplate
	 */
	private $formTemplate;

	function setUp(){
		$this->form         = formBuilder::createForm();
		$this->formTemplate = new formBuilderTemplate($this->form);
	}

	static function setUpBeforeClass(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));
	}

	// -------------------------------------------------

	function test_templates_loadTemplate(){
		$formTemplate = new formBuilderTemplate($this->form);
		$formTemplate->loadTemplate('Some template test');
		$this->assertEquals('Some template test', $formTemplate->render());
	}

	function test_templates_localOverrideTemplate(){
		$formTemplate = new formBuilderTemplate($this->form);
		$this->assertEquals('Some template test', $formTemplate->render('Some template test'));
	}

	function test_templates_formTitle(){
		$template = '{formTitle}';

		$this->assertEquals('', $this->formTemplate->render($template));

		$form         = formBuilder::createForm('foo');
		$formTemplate = new formBuilderTemplate($form);
		$this->assertEquals('foo', $formTemplate->render($template));
	}

	function test_templates_formBegin(){
		$html = $this->formTemplate->render('{form}');
		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_templates_formBegin_hiddenTrue(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));
		$html = $this->formTemplate->render('{form hidden="true"}');

		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
		$this->assertContains($foo->render(), $html);
		$this->assertNotContains($bar->render(), $html);
	}

	function test_templates_formBegin_hiddenFalse(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));
		$html = $this->formTemplate->render('{form hidden="false"}');

		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
		$this->assertNotContains($foo->render(), $html);
		$this->assertNotContains($bar->render(), $html);
	}

	function test_templates_formBegin_miscAttributes(){
		$html = $this->formTemplate->render('{form foo="bar" red="green"}');
		$this->assertContains('<form method="post" foo="bar" red="green">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_templates_formEnd(){
		$this->assertEquals('</form>', $this->formTemplate->render('{/form}'));
	}

	function test_templates_fields_default(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->formTemplate->render('{fields}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_templates_fields_full(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->formTemplate->render('{fields display="full"}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_templates_fields_fields(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->formTemplate->render('{fields display="fields"}');
		$this->assertContains($foo->renderField(), $template, "The rendered template contains the foo field");
		$this->assertNotContains($foo->renderLabel(), $template, "The rendered template doesn't contain the foo label");
		$this->assertContains($bar->renderField(), $template, "The rendered template contains the bar field");
		$this->assertNotContains($bar->renderLabel(), $template, "The rendered template doesn't contain the bar label");
	}

	function test_templates_fields_labels(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));

		$template = $this->formTemplate->render('{fields display="labels"}');
		$this->assertContains($foo->renderLabel(), $template, "The rendered template contains the foo label");
		$this->assertNotContains($foo->renderField(), $template, "The rendered template doesn't contain the foo field");
		$this->assertContains($bar->renderLabel(), $template, "The rendered template contains the bar label");
		$this->assertNotContains($bar->renderField(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_templates_fields_hidden(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text')));

		$template = $this->formTemplate->render('{fields display="hidden"}');
		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($cat->render(), $template, "The rendered template contains the cat field");
		$this->assertNotContains($bar->render(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_templates_fields_invalidOption(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->assertEquals('', $this->formTemplate->render('{fields display="INVALID"}'));
	}

	function test_templates_field_default(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->render(), $this->formTemplate->render('{field name="foo"}'));
	}

	function test_templates_field_full(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->render(), $this->formTemplate->render('{field name="foo" display="full"}'));
	}

	function test_templates_field_label(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->renderLabel(), $this->formTemplate->render('{field name="foo" display="label"}'));
	}

	function test_templates_field_field(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals($foo->renderField(), $this->formTemplate->render('{field name="foo" display="field"}'));
	}

	function test_templates_field_invalidFieldName(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals('', $this->formTemplate->render('{field name="invalid"}'));
	}

	function test_templates_field_invalidDisplay(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->assertEquals('', $this->formTemplate->render('{field name="foo" display="invalid"}'));
	}

	function test_templates_fieldsetBeginNoLegend(){
		$this->assertEquals('<fieldset>', $this->formTemplate->render('{fieldset}'));
	}

	function test_templates_fieldsetBeginEmptyLegend(){
		$this->assertEquals('<fieldset>', $this->formTemplate->render('{fieldset legend=""}'));
	}

	function test_templates_fieldsetBeginLegend(){
		$this->assertEquals('<fieldset><legend>foo</legend>', $this->formTemplate->render('{fieldset legend="foo"}'));
	}

	function test_templates_fieldsetEnd(){
		$this->assertEquals('</fieldset>', $this->formTemplate->render('{/fieldset}'));
	}

	function test_templates_noneFormBuilderTag(){
		$this->assertEquals('{localvars var="foo"}', $this->formTemplate->render('{localvars var="foo"}'));
	}

	function test_templates_complexTemplate(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$template = '{fieldset legend="test"}{fieldset}{field name="foo"}{/fieldset}{/fieldset}';
		$expected = '<fieldset><legend>test</legend><fieldset>'.$foo->render().'</fieldset></fieldset>';
		$this->assertEquals($expected, $this->formTemplate->render($template));
	}

	function test_templates_fieldsLoop(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->formTemplate->render('{fieldsLoop}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_listedFields(){
		$this->form->addField($foo = fieldBuilder::createField('foo'));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$this->form->addField($dog = fieldBuilder::createField('dog'));
		$this->form->addField($cat = fieldBuilder::createField('cat'));
		$template = $this->formTemplate->render('{fieldsLoop list="foo,dog"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$dog->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_null(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->formTemplate->render('{fieldsLoop editStrip="null"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li><li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_true(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->formTemplate->render('{fieldsLoop editStrip="true"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li>', $template);
		$template = $this->formTemplate->render('{fieldsLoop editStrip="1"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$foo->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_editStrip_false(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE)));
		$this->form->addField($bar = fieldBuilder::createField('bar'));
		$template = $this->formTemplate->render('{fieldsLoop editStrip="false"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
		$template = $this->formTemplate->render('{fieldsLoop editStrip="0"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_listedFieldsAndEditStrip(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => TRUE)));
		$this->form->addField($dog = fieldBuilder::createField(array('name' => 'dog', 'showInEditStrip' => FALSE)));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'showInEditStrip' => TRUE)));
		$template = $this->formTemplate->render('{fieldsLoop editStrip="true" list="foo,bar"}<li>{field}</li>{/fieldsLoop}');
		$this->assertEquals('<li>'.$bar->render().'</li>', $template);
	}

	function test_templates_fieldsLoop_showHiddenTrue(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text')));
		$template = $this->formTemplate->render('{fieldsLoop showHidden="true"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
		$template = $this->formTemplate->render('{fieldsLoop showHidden="1"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_fieldsLoop_showHiddenFalse(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text')));
		$template = $this->formTemplate->render('{fieldsLoop showHidden="false"}<li>{field}</li>{/fieldsLoop}');
		$this->assertNotContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
		$template = $this->formTemplate->render('{fieldsLoop showHidden="0"}<li>{field}</li>{/fieldsLoop}');
		$this->assertNotContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_fieldsLoop_showHiddenWithList(){
		$this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden')));
		$this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden')));
		$this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text')));
		$template = $this->formTemplate->render('{fieldsLoop showHidden="true" list="foo,cat"}<li>{field}</li>{/fieldsLoop}');
		$this->assertContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains($cat->render(), $template);
	}

	function test_templates_rowLoop(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Create a new form
		$form         = formBuilder::createForm("rowLoop", array('table' => 'fieldBuilderTest', 'limit' => 3));
		$formTemplate = new formBuilderTemplate($form);

		// Add a field
		$form->addField($foo = fieldBuilder::createField(array('name' => 'name')));

		// Process the template
		$template = $formTemplate->render('{rowLoop}<li>{field display="field" name="name"}</li>{/rowLoop}');

		// Assertions
		$this->assertEquals('<li>'.$foo->renderField().'</li><li>'.$foo->renderField().'</li><li>'.$foo->renderField().'</li>', $template);
	}

}
 