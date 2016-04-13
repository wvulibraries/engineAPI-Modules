# Form Builder

Form builder is a way to dynamically create input forms that link directly to a database and can also connect to some other databases for linked table data.  
The forms have callbacks and process the posted information.  You can edit form titles and add other form variables and fields.

## MYSQL

Form Builder requires that you setup your MySQL tables in a similar way to your form.  If you do not there is a chance that your form will not insert or update properly.  Any additional information that you need to post, pull from databases, or update you may want to do with the user of callbacks and may require a separate link table.  Please read the documentation carefully as some fieldOptions do require that you setup a link table or have very specific instructions for using those aspects of the form builder.  

## formBuilder Options

- formEncoding       [str]
 - Optional form encoding (sets the enctype attribute on the <form> tag for example with file fields)
- browserValidation  [bool]
 - Set to false to disable browser-side form validation (default: true)
- insertTitle        [str]
 - Form title for insertForm (default: $formName as passed to formBuilder::createForm())
- updateTitle        [str]
 - Form title for updateForm (default: $formName as passed to formBuilder::createForm())
- editTitle          [str]
 - Form title for editTable (default: $formName as passed to formBuilder::createForm())
- templateDir        [str]
 - The directory where our form templates live (default: 'formTemplates' next to the module)
- template           [str]
 - The template name to load for this template (default: 'default')
- ajaxHandlerURL     [str]
 - URL for formBuilder ajax handler (default: the current URL)
- insertFormCallback [str]
 - Custom JavaScript function name to call to retrieve the updateForm in an expandable editTable (default: none)
- submitTextInsert   [str]
 - Button text for submit button on insertForm (default: 'Insert')
- submitTextUpdate   [str]
 - Button text for submit button on updateForm (default: 'Update')
- deleteTextUpdate   [str]
 - Button text for delete button on updateForm (default: 'Delete')
- submitTextEdit     [str]
 - Button text for submit button on editTable (default: 'Update')
- expandable         [bool]
 - Sets editTable as an 'expandable' editTable with drop-down update form (default: true)

## Field Options:

These are the default options that form builder knows how to deal with.  These are not the only options you can use, custom items however may effect the functionality of the end form so please test all custom options before using your form builder form in production.  

- blankOption         [bool|str] Include a blank option on 'select' field. If it's a string, will be the label for the blank options (default: false)
- disabled            [bool]     Disable the field
- disableStyling      [bool]     If true, then ignores all CSS styling (ie fieldClass, fieldCSS, labelClass, & fieldCSS) (default: falsE)
- duplicates          [bool]     Allow duplicated (default: true)
- fieldClass          [str]      CSS Classes to add to the field
- fieldCSS            [str]      CSS Style to add to the field
- fieldID             [str]      id attribute for the field
- fieldMetadata       [array]    Array of key->value pairs to be added to the field through data- attributes
- hash                [str]      The mhash algorithm to use for password fields (default: sha512)
- help                [array]    Array of field help options
  - type             [str]      The type of help: modal, newWindow, hover, tooltip (default: tooltip)
  - text             [str]      Plaintext to display
  - url              [str]      URL of content
  - file             [str]      Local file to pull content from
- label               [str]      The label for the field (default: {} to field's name)
- labelClass          [str]      CSS Classes to add to the label
- labelCSS            [str]      CSS Classes to add to the label
- labelID             [str]      id attribute for the label
- labelMetadata       [array]    Array of key->value pairs to be added to the label through data- attributes
- linkedTo            [array]    Array of metadata denoting either a one-to-many or many-to-many relationship
  - foreignTable     [str]      The table where the values for this field live
  - foreignKey       [str]      The column on the foreignTable which contains the value
  - foreignLabel     [str]      The column on the foreignTable which contains the label
  - foreignOrder     [str]      Optional ORDER BY clause (default: '{foreignLabel} ASC')
  - foreignWhere     [str]      Optional WHERE clause
  - foreignLimit     [str]      Optional LIMIT clause
  - foreignSQL       [str]      Option raw SELECT SQL to be used. (1st column is treated as foreignKey and 2nd as foreignLabel)
  - linkTable        [str]      many-to-many: Linking table name
  - linkLocalField   [str]      many-to-many: Linking table column where the local key lives
  - linkForeignField [str]      many-to-many: Linking table column where the foreign key lives
- multiple            [bool]     Sets 'multiple' on a select field (default: false)
- options             [array]    Array of field options for select, checkbox, radio, and boolean
- placeholder         [str]      Text to put in field's placeholder="" attribute
- primary             [bool]     Sets the field as a primary field (multiple primary fields are allowed) (default: false)
- readonly            [bool]     Sets the field to be read-only (default: false)
- required            [bool]     Sets the field as required (default: false)
- showIn              [array]    Show/Hide the field in specified forms (default: array of all types)
- type                [str]      The type of field (see list of field types below)
- validate            [str]      The validate method to check the value against
- value               [str]      The initial value for this field

## Field types:

These are the default options for field types.  If you see need for a new one in the future then please add it to a request.  Changing a field type to an updated HTML5 form type may work, but could cause conflicts with form builder so you may use a custom type, but test your solution carefully as it may not be recognized by form builder.

- bool        Alias for 'boolean'
- boolean     Boolean (Yes/No) field
 - options
   - type    [string] Type of boolean field: check, checkbox, radio, select (default: select)
   - labels  [array]  Labels to use for 'Yes' and 'No' (default: ['NO\_LABEL','YES\_LABEL'])
- button      Standard button
- checkbox    Checkbox group
 - options   Array of value->label pairs to be displayed
- color       HTML5 color picker    dependent on browser support
- date        HTML5 date picker     dependent on browser support -- Converts and saves as unix time (using strtotime)
- datetime    HTML5 datetime picker dependent on browser support
- dropdown    Alias for 'select'
- email       HTML5 email field
- file        File field
- hidden      Hidden field (will be rendered just below <form> tag)
- image       HTML5 image field dependent on browser support
- month       HTML5 month picker dependent on browser support
- multiSelect multiSelect field - JQuery Dependent and Requires a custom setup with LinkTable
- mulitText   Dynamic MulitText Option for creating forms with dynamic typed options - JQuery Dependent and Custom Setup Required see documentation below
- number      HTML5 number field dependent on browser support
- password    Password field (will render a confirmation field as well)
- plaintext   Plaintext field with support for text-replacements note: replacements are case sensitive
- range       HTML5 range field dependent on browser support
- radio       Radio group
 - options   Array of value->label pairs to be displayed
- reset       Form reset button
- search      HTML5 search field
- select      \<select\> field
 - options   String of options or Array of value->label pairs to be displayed
- string      Alias for stext
- submit      Form submit button
- delete      Form submit button to delete the record
- text        simple \<input\> field
- textarea    Full \<textarea\>
- tel         HTML5 tel field
- time        HTML5 time picker dependent on browser support
- url         HTML5 url field
- week        HTML5 week picker dependent on browser support
- wysiwyg     Full WYSIWYG editor

## Examples

Below is an example of a form builder that has only 2 fields.  Each field will be build from the addField function.
You can see examples of where to put the form options, field options, and field types using the example below.

```php
    //get formID if one is provided
    $id = isset($_GET['MYSQL']['id']) ? $_GET['MYSQL']['id'] : '');

    // create customer form
    $form = formBuilder::createForm('formName');
    $form->linkToDatabase( array(
        'table' => 'formDatabaseTable'
    ));

    if(!is_empty($_POST) || session::has('POST')) {
        $processor = formBuilder::createProcessor();
        $processor->processPost();
    }

    // form titles
    $form->insertTitle = "Insert Title";
    $form->editTitle   = "Edit Title";
    $form->updateTitle = "Update Title";

    // form information
    $form->addField(array(
        'name'       => 'ID',
        'type'       => 'hidden',
        'value'      => $id,
        'primary'    => TRUE,
        'fieldClass' => 'id',
        'showIn'     => array(formBuilder::TYPE_INSERT, formBuilder::TYPE_UPDATE),
    ));

    $form->addField(array(
        'name'     => 'fieldName',
        'label'    => 'Field Label',
        'required' => TRUE
    ));
```

Below is an example of how you call the form you created by using the formName.  The one below is a unique example that allows the form to automatically decide between an update or an insert form based on if the primary key field has an id and it matches something within the database.

```html
{form name="formName" display="form"}
```

Below is an example of what an edit table will appear.  This will allow you to edit multiple fields rather quickly.  It is not a good option for particularly large forms.

```html
{form name="formName" display="edit"}
```

## Asset Pipelines / Form Templates

** Needs Documented

## MultiText

The example below is an idea of how a typical multi text box will be setup.  The multi text box will rely on MySQL tables being setup properly and perform a link between multiple tables.
For example lets say we are creating questions for a test and we need to have one that has the correct answer.  We could setup a database structure using form builder like the options below.      


#### answers Table

```sql
DROP TABLE IF EXISTS `question`;
CREATE TABLE `question`(
    `ID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `question` varchar(300) NOT NULL,
    PRIMARY KEY(`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- example table for storing the answers or the multi-text values
-- this is always going to need a value slot that accepts varchar and a boolean or tinyint (( in mysql boolean references are treated as 1/0 ))
DROP TABLE IF EXISTS `answer`;
CREATE TABLE `answer`(
    `aID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    `value` varchar(300) NOT NULL,
    `default` boolean NOT NULL,
    PRIMARY KEY(`aID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- example setup of the link table for multi-text example
DROP TABLE IF EXISTS `qaLinkTable`;
CREATE TABLE `qaLinkTable`(
    `ID` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    `questionID` tinyint(3) NOT NULL,
    `answerID` tinyint(3) NOT NULL,
    PRIMARY KEY(`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

#### MultiText Add Field looks like this

```php
$form->addField(array(
    'name'              => 'answer', // same as foreginTable
    'type'              => 'multiText',
    'label'             => 'Answers',
    'fieldID'           => 'answers',
    'fieldClass'        => 'answersFieldClass',
    'showIn'            => array(formBuilder::TYPE_INSERT, formBuilder::TYPE_UPDATE),
    'multiTextSettings' => array(
                              'foreignTable'   => 'answer',
                              'foreignKey'	   => 'aID',
                              'foreignColumns' => '`value`,`default`' ),
    'linkedTo'          => array(
                              'foreignTable'     => 'answer',
                              'foreignKey'       => 'aID',
                              'foreignLabel'     => 'value',
                              'linkTable'        => 'qaLinkTable',
                              'linkLocalField'   => 'questionID',
                              'linkForeignField' => 'answerID' ),
));
```

#### Entire Form for this Example Looks like this

```php
<?php
    formBuilder::ajaxHandler();

    $form = formBuilder::createForm('formName');
    $form->linkToDatabase( array(
        'table' => 'question'
    ));

	$id = isset($_GET['MYSQL']['id']) ? $_GET['MYSQL']['id'] : "";

    if(!is_empty($_POST) || session::has('POST')) {
        $processor = formBuilder::createProcessor();
        $processor->processPost();
    }

    // form titles
    $form->insertTitle = "Insert Title";
    $form->editTitle   = "Edit Title";
    $form->updateTitle = "Update Title";

    // form information
    $form->addField(array(
        'name'       => 'ID',
        'type'       => 'hidden',
        'primary'    => TRUE,
        'fieldClass' => 'id',
		'value'      => $id,
        'showIn'     => array(formBuilder::TYPE_INSERT, formBuilder::TYPE_UPDATE),
    ));

    $form->addField(array(
        'name'     => 'name',
        'label'    => 'Question Name:',
        'required' => TRUE
    ));

    $form->addField(array(
        'name'     => 'question',
        'label'    => 'The Question:',
        'required' => TRUE
    ));

    $form->addField(array(
        'name'       => 'answer',
        'type'       => 'multiText',
        'label'      => 'Answers',
        'fieldID'    => 'answers',
        'fieldClass' => 'answersFieldClass',
        'showIn'     => array(formBuilder::TYPE_INSERT, formBuilder::TYPE_UPDATE),
        'multiTextSettings'  => array(
                            'foreignTable'   => 'answer',
			                      'foreignKey'	   => 'aID',
                            'foreignColumns' => '`value`,`default`'
        ),
    		'linkedTo' => array(
                      			'foreignTable'     => 'answer',
                      			'foreignKey'       => 'aID',
                      			'foreignLabel'     => 'value',
                      			'linkTable'        => 'qaLinkTable',
                      			'linkLocalField'   => 'questionID',
                      			'linkForeignField' => 'answerID'
    		)
    ));

    templates::display('header');
?>

<h2> test </h2>

{form name="formName" display="form"}
```

## CallBack

** Needs Documented

## MultiSelect
