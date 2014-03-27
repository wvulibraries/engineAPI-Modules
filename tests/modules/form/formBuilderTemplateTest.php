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

	function test_localOverrideTemplate(){
		$formTemplate = new formBuilderTemplate($this->form);
		$this->assertEquals('Some template test', $formTemplate->render('Some template test'));
	}

	function test_formTitle(){
		$this->form->template = '{formTitle}';
		$this->assertEquals('foo', $this->form->display('insert', array('title'=>'foo')));

		$this->assertEquals(' Insert', $this->form->display('insert'));

		$this->form->insertTitle = 'bar';
		$this->assertEquals('bar', $this->form->display('insert'));

		$form = formBuilder::createForm('foo');
		$form->template = '{formTitle}';
		$this->assertEquals('foo Insert', $form->display('insert'));
	}

	function test_formBegin(){
		$html = $this->formTemplate->render('{form}');
		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_formBegin_hiddenTrue(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text'))));
		$html = $this->formTemplate->render('{form hidden="true"}');

		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
		$this->assertContains($foo->render(), $html);
		$this->assertNotContains($bar->render(), $html);
	}

	function test_formBegin_hiddenFalse(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text'))));
		$html = $this->formTemplate->render('{form hidden="false"}');

		$this->assertContains('<form method="post">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
		$this->assertNotContains($foo->render(), $html);
		$this->assertNotContains($bar->render(), $html);
	}

	function test_formBegin_miscAttributes(){
		$html = $this->formTemplate->render('{form foo="bar" red="green" data-cat="dog"}');
		$this->assertContains('<form method="post" foo="bar" red="green" data-cat="dog">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_formBegin_formAttributes(){
		$this->formTemplate->formAttributes['foo'] = 'bar';
		$this->formTemplate->formAttributes['cat'] = 'dog';

		$html = $this->formTemplate->render('{form}');

		$this->assertContains('<form method="post" foo="bar" cat="dog">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_formBegin_dataAttributes(){
		$this->formTemplate->formDataAttributes['foo'] = 'bar';
		$this->formTemplate->formDataAttributes['cat'] = 'dog';

		$html = $this->formTemplate->render('{form}');

		$this->assertContains('<form method="post" data-foo="bar" data-cat="dog">', $html);
		$this->assertContains('<input type="hidden" name="__formID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfID"', $html);
		$this->assertContains('<input type="hidden" name="__csrfToken"', $html);
	}

	function test_formEnd(){
		$this->assertEquals('</form>', $this->formTemplate->render('{/form}'));
	}

	function test_fields_default(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));

		$template = $this->formTemplate->render('{fields}');

		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_fields_full(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));

		$template = $this->formTemplate->render('{fields display="full"}');

		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($bar->render(), $template, "The rendered template contains the bar field");
	}

	function test_fields_fields(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));

		$template = $this->formTemplate->render('{fields display="fields"}');

		$this->assertContains($foo->renderField(), $template, "The rendered template contains the foo field");
		$this->assertNotContains($foo->renderLabel(), $template, "The rendered template doesn't contain the foo label");
		$this->assertContains($bar->renderField(), $template, "The rendered template contains the bar field");
		$this->assertNotContains($bar->renderLabel(), $template, "The rendered template doesn't contain the bar label");
	}

	function test_fields_labels(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));

		$template = $this->formTemplate->render('{fields display="labels"}');

		$this->assertContains($foo->renderLabel(), $template, "The rendered template contains the foo label");
		$this->assertNotContains($foo->renderField(), $template, "The rendered template doesn't contain the foo field");
		$this->assertContains($bar->renderLabel(), $template, "The rendered template contains the bar label");
		$this->assertNotContains($bar->renderField(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_fields_hidden(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'text'))));

		$template = $this->formTemplate->render('{fields display="hidden"}');

		$this->assertContains($foo->render(), $template, "The rendered template contains the foo field");
		$this->assertContains($cat->render(), $template, "The rendered template contains the cat field");
		$this->assertNotContains($bar->render(), $template, "The rendered template doesn't contain the bar field");
	}

	function test_fields_invalidOption(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));
		$this->assertEquals('', $this->formTemplate->render('{fields display="INVALID"}'));
	}

	function test_field_default(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals($foo->render(), $this->formTemplate->render('{field name="foo"}'));
	}

	function test_field_full(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals($foo->render(), $this->formTemplate->render('{field name="foo" display="full"}'));
	}

	function test_field_label(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals($foo->renderLabel(), $this->formTemplate->render('{field name="foo" display="label"}'));
	}

	function test_field_field(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals($foo->renderField(), $this->formTemplate->render('{field name="foo" display="field"}'));
	}

	function test_field_noFieldName(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals('', $this->formTemplate->render('{field}'));
	}

	function test_field_invalidFieldName(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals('', $this->formTemplate->render('{field name="invalid"}'));
	}

	function test_field_invalidDisplay(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertEquals('', $this->formTemplate->render('{field name="foo" display="invalid"}'));
	}

	function test_field_disabledPrimaryField(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name'=>'foo','primary'=>TRUE))));
		$rendered = $this->formTemplate->render('{field name="foo"}');
		$this->assertTag(array(
			'tag'        => 'input',
			'attributes' => array(
				'disabled' => TRUE
			)
		), $rendered);
	}

	function test_fieldsetBeginNoLegend(){
		$this->assertEquals('<fieldset>', $this->formTemplate->render('{fieldset}'));
	}

	function test_fieldsetBeginEmptyLegend(){
		$this->assertEquals('<fieldset>', $this->formTemplate->render('{fieldset legend=""}'));
	}

	function test_fieldsetBeginLegend(){
		$this->assertEquals('<fieldset><legend>foo</legend>', $this->formTemplate->render('{fieldset legend="foo"}'));
	}

	function test_fieldsetEnd(){
		$this->assertEquals('</fieldset>', $this->formTemplate->render('{/fieldset}'));
	}

	function test_noneFormBuilderTag(){
		$this->assertEquals('{localvars var="foo"}', $this->formTemplate->render('{localvars var="foo"}'));
	}

	function test_complexTemplate(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));

		$template = '{fieldset legend="test"}{fieldset}{field name="foo"}{/fieldset}{/fieldset}';
		$expected = '<fieldset><legend>test</legend><fieldset>'.$foo->render().'</fieldset></fieldset>';

		$this->assertEquals($expected, $this->formTemplate->render($template));
	}

	function test_fieldsLoop(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));

		$template = $this->formTemplate->render('{form}{fieldsLoop}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('<li>'.$foo->render().'</li>', $template);
		$this->assertContains('<li>'.$bar->render().'</li>', $template);
	}

	function test_fieldsLoop_listedFields(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('foo')));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('bar')));
		$this->assertTrue($this->form->addField($dog = fieldBuilder::createField('dog')));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField('cat')));

		$template = $this->formTemplate->render('{form}{fieldsLoop list="foo,dog"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop list="foo,dog"}}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('<li>'.$foo->render().'</li>', $template);
		$this->assertNotContains('<li>'.$bar->render().'</li>', $template);
		$this->assertContains('<li>'.$dog->render().'</li>', $template);
		$this->assertNotContains('<li>'.$cat->render().'</li>', $template);
	}

	function test_fieldsLoop_editStrip_null(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => FALSE))));

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="null"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="null"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('<li>'.$foo->render().'</li>', $template);
		$this->assertContains('<li>'.$bar->render().'</li>', $template);
	}

	function test_fieldsLoop_editStrip_true(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => FALSE))));

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="true"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="true"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('<li>'.$foo->render().'</li>', $template);
		$this->assertNotContains('<li>'.$bar->render().'</li>', $template);

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="1"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="1"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('<li>'.$foo->render().'</li>', $template);
		$this->assertNotContains('<li>'.$bar->render().'</li>', $template);
	}

	function test_fieldsLoop_editStrip_false(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => TRUE))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => FALSE))));

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="false"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="false"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertNotContains('<li>'.$foo->render().'</li>', $template);
		$this->assertContains('<li>'.$bar->render().'</li>', $template);

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="0"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="0"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertNotContains('<li>'.$foo->render().'</li>', $template);
		$this->assertContains('<li>'.$bar->render().'</li>', $template);
	}

	function test_fieldsLoop_listedFieldsAndEditStrip(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'showInEditStrip' => FALSE))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'showInEditStrip' => TRUE))));
		$this->assertTrue($this->form->addField($dog = fieldBuilder::createField(array('name' => 'dog', 'showInEditStrip' => FALSE))));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'showInEditStrip' => TRUE))));

		$template = $this->formTemplate->render('{form}{fieldsLoop editStrip="true" list="foo,bar"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop editStrip="true" list="foo,bar"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertNotContains('<li>'.$foo->render().'</li>', $template);
		$this->assertContains('<li>'.$bar->render().'</li>', $template);
		$this->assertNotContains('<li>'.$dog->render().'</li>', $template);
		$this->assertNotContains('<li>'.$cat->render().'</li>', $template);
	}

	function test_fieldsLoop_showHiddenTrue(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text'))));

		$template = $this->formTemplate->render('{form}{fieldsLoop showHidden="true"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop showHidden="true"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains('<li>'.$cat->render().'</li>', $template);

		$template = $this->formTemplate->render('{form}{fieldsLoop showHidden="1"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop showHidden="1"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains($foo->render(), $template);
		$this->assertContains($bar->render(), $template);
		$this->assertContains('<li>'.$cat->render().'</li>', $template);
	}

	function test_fieldsLoop_showHiddenFalse(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text'))));

		$template = $this->formTemplate->render('{form}{fieldsLoop showHidden="false"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop showHidden="false"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertNotContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains('<li>'.$cat->render().'</li>', $template);

		$template = $this->formTemplate->render('{form}{fieldsLoop showHidden="0"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop showHidden="0"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertNotContains('<li>'.$foo->render().'</li>', $template);
		$this->assertNotContains('<li>'.$bar->render().'</li>', $template);
		$this->assertContains('<li>'.$cat->render().'</li>', $template);
	}

	function test_fieldsLoop_showHiddenWithList(){
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField(array('name' => 'foo', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField(array('name' => 'bar', 'type' => 'hidden'))));
		$this->assertTrue($this->form->addField($cat = fieldBuilder::createField(array('name' => 'cat', 'type' => 'text'))));

		$template = $this->formTemplate->render('{form}{fieldsLoop showHidden="true" list="foo,cat"}<li>{field}</li>{/fieldsLoop}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{fieldsLoop showHidden="true" list="foo,cat"}', $template);
		$this->assertNotContains('{field}', $template);
		$this->assertNotContains('{/fieldsLoop}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains($foo->render(), $template);
		$this->assertNotContains($bar->render(), $template);
		$this->assertContains('<li>'.$cat->render().'</li>', $template);
	}

	function test_rowLoop(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Create a new form
		$form         = formBuilder::createForm("rowLoop", array('table' => 'fieldBuilderTest', 'limit' => 3));
		$formTemplate = new formBuilderTemplate($form);

		// Add a field
		$this->assertTrue($form->addField($foo = fieldBuilder::createField(array('name' => 'name'))));

		// Process the template
		$template = $formTemplate->render('{form}{rowLoop}<li>{field display="field" name="name"}</li>{/rowLoop}{/form}');

		// Assertions
		$this->assertTag(array(
			'children' => array(
				'count' => 3,
				'only'  => array(
					'tag'      => 'li',
					'children' => array(
						'only' => array(
							'tag' => 'input'
						)
					)
				)
			)
		), $template, 'It contains 3 <input> tags');
	}

	function test_rowLoop_invalidTable(){
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Create a new form
		$form         = formBuilder::createForm("rowLoop");
		$formTemplate = new formBuilderTemplate($form);

		// Add a field
		$this->assertTrue($form->addField($foo = fieldBuilder::createField('name')));

		// Process the template
		$template = $formTemplate->render('{form}{rowLoop}<li>{field display="field" name="name"}</li>{/rowLoop}{/form}');

		// Assertions
		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{rowLoop}', $template);
		$this->assertNotContains('<li>', $template);
		$this->assertNotContains('{field display="field" name="name"}', $template);
		$this->assertNotContains('</li>', $template);
		$this->assertNotContains('{/rowLoop}', $template);
		$this->assertNotContains('{/form}', $template);
	}

	function test_rowcount() {
		$this->markTestIncomplete();
		// Reset database
		db::getInstance()->appDB->query(file_get_contents(__DIR__.'/fieldBuilderTest.sql'));

		// Create a new form
		$form         = formBuilder::createForm("rowLoop", array('table' => 'fieldBuilderTest', 'limit' => 3));
		$formTemplate = new formBuilderTemplate($form);

		// Process the template
		$this->assertEquals('3', $formTemplate->render('{rowCount}'), "'{rowCount}' is 3");
		$this->assertEquals('3', $formTemplate->render('{rowcount}'), "'{rowcount}' is 3");
	}

	function test_fieldcount() {
		$this->markTestIncomplete();
		$this->assertTrue($this->form->addField($foo = fieldBuilder::createField('name')));
		$this->assertEquals('1', $this->formTemplate->render('{fieldCount}'), "'{fieldCount}' is 1");
		$this->assertEquals('1', $this->formTemplate->render('{fieldcount}'), "'{fieldcount}' is 1");

		$this->assertTrue($this->form->addField($bar = fieldBuilder::createField('ID')));
		$this->assertEquals('2', $this->formTemplate->render('{fieldCount}'), "'{fieldCount}' is 2");
		$this->assertEquals('2', $this->formTemplate->render('{fieldcount}'), "'{fieldcount}' is 2");
	}

	function test_formErrors() {
		$form         = formBuilder::createForm('errorTest1');
		$formTemplate = new formBuilderTemplate($form);
		$form->formError('Test msg1', errorHandle::ERROR, $form->formName.'_'.$formTemplate->formType);
		$this->assertEquals('<ul class="errorPrettyPrint"><li class="errorMessage">Test msg1</li></ul>', $formTemplate->render('{formErrors}'));
	}

	function test_wrappedFormErrors() {
		$form         = formBuilder::createForm('errorTest2');
		$formTemplate = new formBuilderTemplate($form);
		$form->formError('Test msg2', errorHandle::ERROR, $form->formName.'_'.$formTemplate->formType);
		$this->assertEquals('<span class="test"><ul class="errorPrettyPrint"><li class="errorMessage">Test msg2</li></ul></span>', $formTemplate->render('{ifFormErrors}<span class="test">{formErrors}</span>{/ifFormErrors}'));
	}

	function test_expandableEditTable_enabled(){
		$this->formTemplate->renderOptions['expandable'] = TRUE;
		$template = $this->formTemplate->render('{form}{ifExpandable}Foo{/ifExpandable}{/form}');

		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{ifExpandable}', $template);
		$this->assertContains('Foo', $template);
		$this->assertNotContains('{/ifExpandable}', $template);
		$this->assertNotContains('{/form}', $template);
	}
	function test_expandableEditTable_disabled(){
		$this->formTemplate->renderOptions['expandable'] = FALSE;
		$template = $this->formTemplate->render('{form}{ifExpandable}Foo{/ifExpandable}{/form}');
		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{ifExpandable}', $template);
		$this->assertNotContains('{/ifExpandable}', $template);
		$this->assertNotContains('{/form}', $template);

		$template = $this->formTemplate->render('{form}Before{ifExpandable}Foo{/ifExpandable}After{/form}');
		$this->assertNotContains('{form}', $template);
		$this->assertNotContains('{ifExpandable}', $template);
		$this->assertNotContains('{/ifExpandable}', $template);
		$this->assertNotContains('{/form}', $template);
		$this->assertContains('BeforeAfter', $template);
	}

}
