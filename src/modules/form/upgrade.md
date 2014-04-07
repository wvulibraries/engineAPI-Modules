## Create new form
### Before
```
// listManagment is tied to a database table
$listObj = new listManagement('TABLE_NAME');
```
### After
```
// formBuilder is tied to a unique form name
$form = formBuilder::createForm('formName');
```

------------------------------------

## Add fields to the form
### Before
```
$form = new listManagement('TABLE_NAME');
$form->addField(array(
    'field' => 'fieldName',
    'label' => 'Field Name',
    'type'  => 'text'
));

```
### After
```
$form = formBuilder::createForm('formName');
$form->addField(array(
    'name'  => 'fieldName',
    'label' => 'Field Name',
    'type'  => 'text'
));
```

------------------------------------

## Display insert/update form
### Before
```
$form = new listManagement('TABLE_NAME');
...
echo $form->displayInsertForm();
```
### After
```
$form = formBuilder::createForm('formName');
...
// Let formBuilder decide what form to display (insert or update)
echo $form->display('form');
-or-
{form name="formName" display="form"}

/*
Explicitly display a form type.
FORM_TYPE can be any of the following:
   - 'insert'
   - 'insertForm'
   - formBuilder::TYPE_INSERT
   - 'update'
   - 'updateForm'
   - formBuilder::TYPE_UPDATE
*/
echo $form->display(FORM_TYPE);
-or-
{form name="formName" display="FORM_TYPE"}
```

------------------------------------

## Display edit table
### Before
```
$form = new listManagement('TABLE_NAME');
...
echo $form->displayInsertForm();
```
### After
```
$form = formBuilder::createForm('formName');
...
// FORM_TYPE can be: 'edit', 'editTable', or formBuilder::TYPE_EDIT
echo $form->display(FORM_TYPE);
-or-
{form name="formName" display="FORM_TYPE"}
```

------------------------------------

## Set the password hashing algortihm
### Before
```
// Forces all password fields to use the same hash
$form = new listManagement('TABLE_NAME');
$form->passwordHash = 'sha512';
$form->addField(array(
    'name' => 'password',
    'type' => 'password',
));
```
### After
```
// Each password field can have its own hash
$form = formBuilder::createForm('formName');
$form->addField(array(
    'name' => 'password',
    'type' => 'password',
    'hash' => 'sha512'
));
```

------------------------------------

## Disable browser-size form validation
### Before
```
// Not supported
```
### After
```
$form = formBuilder::createForm('formName');
$form->browserValidation = FALSE;
```

------------------------------------

## Set additional attributes on the <form> tag
### Before
```
// Limited to rel and rev attributes
$form = new listManagement('TABLE_NAME');
$form->rel = 'sha512';
$form->rev = 'sha512';
```
### After
```
/*
Not currently supported, but the code is in place and being used internally.
It just needs a public interface and accept any number of HTML attributes as well as data-* attributes
*/
```

------------------------------------

## Set form labels
### Before
```
// Not supported - Up to the overal page template
```
### After
```
$form = formBuilder::createForm('formName');
$form->insertTitle = 'Insert form title';
$form->updateTitle = 'Update form title';
$form->editTitle   = 'Edit table title';
```

------------------------------------

## Set form templates
### Before
```
// Not supported
```
### After
```
$form = formBuilder::createForm('formName');
$form->templateDir = '/path/to/template/directoy/'; // Base path to template directories
$form->template    = 'default';                     // The specific template to use
```

------------------------------------

## Set primary field
### Before
```
// Limited to one field
$form = new listManagement('TABLE_NAME');
$form->primaryKey = 'username';
```
### After
```
// Can be any number of fields (for this example: ID & username)
$form = formBuilder::createForm('formName');
$form->addField(array(
    'primary' => TRUE,
    'name' => 'ID'
));
$form->addField(array(
    'primary' => TRUE,
    'name' => 'username'
));
```

------------------------------------

## Set form submit button text
### Before
```
$form = new listManagement('TABLE_NAME');
$form->updateButtonText = "Update";
$form->insertButtonText = "Insert";
```
### After
```
$form = formBuilder::createForm('formName');
$form->submitTextInsert = 'Insert';
$form->submitTextUpdate = 'Update';
$form->submitTextEdit   = 'Update';
```

------------------------------------

## Use callbacks
### Before
```
$form = new listManagement('TABLE_NAME');
$form->callbackObj    = NULL;
$form->insertCallback = NULL;
$form->updateCallback = NULL; // Not yet Implimented
$form->deleteCallback = NULL;
...
$form->insert();
```
### After
```
$processor = formBuilder::createProcessor();
$processor->setCallback('callbackTriggerName', array($obj,'func'));
$processor->processPost();
```
Available triggers:
 - beforeInsert
 - doInsert
 - afterInsert
 - beforeUpdate
 - doUpdate
 - afterUpdate
 - beforeEdit
 - doEdit
 - afterEdit
 - beforeDelete
 - doDelete
 - afterDelete
 - onSuccess
 - onFailure

------------------------------------

## General form options
### Before
```
$listObj = new listManagement($tableName);
$listObj->postTarget = $_SERVER['REQUEST_URI'];
$listObj->sortable   = TRUE;
```
### After
```
$form = formBuilder::createForm('formName');
$form->formEncoding;
$form->ajaxHandlerURL;
$form->insertFormCallback;
$form->expandable;
```

------------------------------------

## Display form errors
### Before
```
// Form errors have to be shown manually
$listObj = new listManagement('tableName');
if(!$form->update(){
    // This will show ALL engine errors
    echo implode("\n", $engine->errorStack['error']));
}

// Form errors have to be displayed manually
```
### After
```
$form = formBuilder::createForm('demo');
...
// Show only errors specific to the form 'demo'
{form name="demo" display="errors"}
```


------------------------------------

## Processess submitted data
### Before
```
$tableName = 'tableName';
$listObj   = new listManagement($tableName);

if(isset($engine->cleanPost['MYSQL'])){
    if(isset($engine->cleanPost['MYSQL'][$tableName.'_submit'])){
        $result = $listObj->insert();
    }elseif(isset($engine->cleanPost['MYSQL'][$tableName.'_update'])){
        $result = $listObj->update();
    }else{
        // POST Error
    }
}
```
### After
```
formBuilder::process();
```
