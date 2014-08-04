<?php

/**
 * Class formBuilder
 *
 * formBuilder Options:
 *  - formEncoding       [str]  Optional form encoding (sets the enctype attribute on the <form> tag for example with file fields)
 *  - browserValidation  [bool] Set to false to disable browser-side form validation (default: true)
 *  - insertTitle        [str]  Form title for insertForm (default: $formName as passed to formBuilder::createForm())
 *  - updateTitle        [str]  Form title for updateForm (default: $formName as passed to formBuilder::createForm())
 *  - editTitle          [str]  Form title for editTable (default: $formName as passed to formBuilder::createForm())
 *  - templateDir        [str]  The directory where our form templates live (default: 'formTemplates' next to the module)
 *  - template           [str]  The template name to load for this template (default: 'default')
 *  - ajaxHandlerURL     [str]  URL for formBuilder ajax handler (default: the current URL)
 *  - insertFormCallback [str]  Custom JavaScript function name to call to retrieve the updateForm in an expandable editTable (default: none)
 *	- submitTextInsert   [str]  Button text for submit button on insertForm (default: 'Insert')
 *  - submitTextUpdate   [str]  Button text for submit button on updateForm (default: 'Update')
 *  - deleteTextUpdate   [str]  Button text for delete button on updateForm (default: 'Delete')
 *  - submitTextEdit     [str]  Button text for submit button on editTable (default: 'Update')
 *  - expandable         [bool] Sets editTable as an 'expandable' editTable with drop-down update form (default: true)
 */
class formBuilder{
	const DEFAULT_FORM_NAME       = '';
	const DEFAULT_FORM_TIMEOUT    = 86400;
	const SESSION_SAVED_FORMS_KEY = 'formBuilderForms';
	const TYPE_UNKNOWN = 0;
	const TYPE_INSERT  = 1;
	const TYPE_UPDATE  = 2;
	const TYPE_EDIT    = 3;

	/**
	 * @var bool Internal 'AJAX Mode' flag
	 */
	private static $ajaxMode = FALSE;

	/**
	 * @var formFields
	 */
	public $fields;

	/**
	 * @var string The base URL where our form assets are located at
	 */
	private $formAssetsURL;

	/**
	 * @var self[] An array of all defined forms
	 */
	private static $formObjects = array();

	/**
	 * @var formProcessor[] An array of all created formProcessor objects (keyed off their formID's)
	 */
	private static $formProcessorObjects = array();

	/**
	 * @var array Array of internal form errors similar to engine's errorStack
	 */
	private static $formErrors = array();

	/**
	 * @var string The name given to this form
	 */
	private $formName;

	/**
	 * @var string the enctype for the generated form
	 */
	public $formEncoding;

	/**
	 * @var bool Boolean flag controlling if browser-side validation should be performed
	 */
	public $browserValidation = TRUE;

	/**
	 * @var string The public title to apply to insert forms
	 */
	public $insertTitle;

	/**
	 * @var string The public title to apply to update forms
	 */
	public $updateTitle;

	/**
	 * @var string The public title to apply to edit tables
	 */
	public $editTitle;

	/**
	 * @var string Filepath to form templates
	 *             This is used in formBuilderTemplate
	 * @TODO Look into remove this tight coupling
	 */
	public $templateDir;

	/**
	 * @var string
	 */
	public $template;

	/**
	 * @var array Array containing metadata linking this form to an underlying database table
	 */
	public $dbOptions;

	/**
	 * @var string Location of Ajax Handler
	 */
	public $ajaxHandlerURL;

	/**
	 * @var string Javascript function to process Ajax call
	 */
	public $insertFormCallback;

	/**
	 * @var array An array to store primary field data for the edit table to facilitate the linking of the editStrip to the updateForm
	 */
	public $editTableRowData = array();

	/**
	 * @var string The text to render into the submit button for insertForm
	 */
	public $submitTextInsert = 'Insert';

	/**
	 * @var string The text to render into the submit button for updateForm
	 */
	public $submitTextUpdate = 'Update';

	/**
	 * @var string The text to render into the delete button for updateForm
	 */
	public $deleteTextUpdate = 'Delete';

	/**
	 * @var string The text to render into the submit button for editTable
	 */
	public $submitTextEdit = 'Update';

	/**
	 * @var bool editTable expandable
	 */
	public $expandable = TRUE;

	/**
	 * Class constructor
	 *
	 * @param string $formName
	 */
	public function __construct($formName){
		$this->fields = new formFields($this);

		// Set default template vars
		$this->templateDir   = __DIR__.DIRECTORY_SEPARATOR.'formTemplates';
		$this->template      = 'default';

		// Set form title vars
		$this->insertTitle   = $formName.' Insert';
		$this->updateTitle   = $formName.' Update';
		$this->editTitle     = $formName.' Edit';

		// Set form name
		$this->formName      = trim(strtolower($formName));

		// Set assets path
		$engineVars          = enginevars::getInstance();
		$this->formAssetsURL = $engineVars->get('formAssetsURL', $engineVars->get('engineInc').DIRECTORY_SEPARATOR.'formBuilderAssets');

		templates::defTempPatterns('/\{form\s+(.+?)\}/', __CLASS__.'::templateMatches', $this);
	}

	/**
	 * Class destructor
	 */
	public function __destruct(){
		unset(self::$formObjects[$this->formName]);
	}

	/**
	 * [Magic Method] Read-only getter for our instance variables
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name){
		return isset($this->$name)
			? $this->$name
			: NULL;
	}

	/**
	 * Map the given form type to the internal int representation
	 * @param string|int $input
	 * @return int
	 */
	public static function getFormType($input){
		switch(trim(strtolower($input))) {
			case self::TYPE_INSERT:
			case 'insert':
			case 'insertform':
				return self::TYPE_INSERT;

			case self::TYPE_UPDATE:
			case 'update':
			case 'updateform':
				return self::TYPE_UPDATE;

			case self::TYPE_EDIT:
			case 'edit':
			case 'edittable':
				return self::TYPE_EDIT;

			default:
				errorHandle::newError(__METHOD__."() Invalid formType! '$input'", errorHandle::DEBUG);
				return self::TYPE_UNKNOWN;
		}
	}

	public static function formError($msg, $type, $scope){
		self::$formErrors[$scope][] = array(
			'message' => $msg,
			'type'    => $type,
		);
	}

	public static function prettyPrintErrors($scope){
		return isset(self::$formErrors[$scope])
			? errorHandle::makePrettyPrint(self::$formErrors[$scope])
			: '';
	}

	/**
	 * Add a field to the form
	 *
	 * @param array|fieldBuilder $field
	 * @return bool
	 */
	public function addField($field){
		// Add the field
		$result = $this->fields->addField($field);

		// If we added it successfully, handle any special cases
		if ($result) {
			if(is_array($field)) $field = $this->fields->getField($field['name']);
			if ($field->type == 'file') {
				$this->formEncoding = 'multipart/form-data';
			}
		}
		return $result;
	}

	/**
	 * AJAX handler
	 * @return null
	 */
	public static function ajaxHandler(){
		try {
			// Set ajaxMode, and if this isn't an AJAX request, we don't care anymore
			self::$ajaxMode = isAJAX();
			if (!self::$ajaxMode) return NULL;

			if (isset($_POST['MYSQL']) && sizeof($_POST['MYSQL'])) {
				// If there's no formID, we don't care
				if (!isset($_POST['MYSQL']['__formID'])) return NULL;

				/*
				 * Extract the RAW data from _POST and pass it to process() for processing
				 *
				 * We use RAW here to avoid double-escaping when we process the data in process()
				 * This may happen because the developer will use process() to handle his own raw data
				 * Since the database module uses prepared statements, manually escaping the POST data is not necessary
				 */
				$errorCode = self::process($_POST['RAW']);

				// Handle the results of process()
				if($errorCode == formProcessor::ERR_OK){
					die(json_encode(array(
						'success'     => TRUE,
						'prettyPrint' => errorHandle::prettyPrint(),
					)));
				}else{
					die(json_encode(array(
						'success'     => FALSE,
						'errorMsg'    => formProcessor::$errorMessages[$errorCode],
						'prettyPrint' => errorHandle::prettyPrint(),
					)));
				}
			}elseif (isset($_GET['MYSQL']) && sizeof($_GET['MYSQL'])) {
				// If there's no formID, we don't care
				if (!isset($_GET['MYSQL']['formID'])) return NULL;

				// Figure out what action to perform
				$action = isset($_GET['MYSQL']['action']) ? $_GET['MYSQL']['action'] : 'getForm';
				switch (trim(strtolower($action))) {
					case 'getform':
					default:
						$savedForm = self::getSavedForm($_GET['MYSQL']['formID']);
						if (!is_array($savedForm)) throw new Exception('Invalid formID!', $savedForm);

						$savedForm      = $savedForm['formBuilder'];
						$formType       = isset($_GET['MYSQL']['formType']) ? $_GET['MYSQL']['formType'] : 'updateForm';
						$rowData        = $savedForm->editTableRowData[$_GET['MYSQL']['rowID']];
						$displayOptions = array('noFormTag' => TRUE);

						// Set field values
						foreach ($rowData as $field => $value) {
							$savedForm->fields->modifyField($field,'value',$value);
						}

						// Return the form
						die(json_encode(array(
							'success' => TRUE,
							'form'    => $savedForm->display($formType, $displayOptions)
						)));
				}
			}
			return NULL;

		} catch (Exception $e) {
			die(json_encode(array(
				'success'   => FALSE,
				'errorMsg'  => $e->getMessage(),
				'errorCode' => $e->getCode(),
			)));
		}
	}

	/**
	 * Retrieves the requested saved form from the session based on $formID
	 * @param string $formID
	 * @return array|int
	 */
	private static function getSavedForm($formID=NULL){
		if (!isset($formID)) return formProcessor::ERR_NO_ID;

		$savedForm = session::get(self::SESSION_SAVED_FORMS_KEY.".$formID");
		if (!$savedForm) return formProcessor::ERR_INVALID_ID;
		return array(
			'formBuilder' => unserialize($savedForm['formBuilder']),
			'formType'    => $savedForm['formType'],
		);
	}

	/**
	 * Process a form submission
	 *
	 * @param string|array $formID Either the formID to process or a POST-like array of data
	 * @return int Result code from formProcessor object
	 */
	public static function process($formID = NULL){
		// If this is an ajax request and we're not in ajaxHandler let ajax handle the request
		if(isAJAX() && !self::$ajaxMode) return self::ajaxHandler();

		// If there's no POST, return
		if(!sizeof($_POST) && !session::has('POST') && !is_array($formID)) return NULL;

		// Catch the case where you pass an array of data in manually
		if (is_array($formID)) {
			$formData = $formID;
			$formID   = NULL;
		}

		// Create the processor and go!
		$processor = self::createProcessor($formID);
		return ($processor instanceof formProcessor)
			? isset($formData) ? $processor->process($formData) : $processor->processPost()
			: $processor;
	}

	/**
	 * Create a formProcessor object for a given formID
	 *
	 * This can be useful if you need to manipulate the formProcessor object before actually processing it
	 *
	 * @param string $formID
	 * @return formProcessor|int formProcessor object or formProcessor error code
	 */
	public static function createProcessor($formID = NULL){
		// If no formID was passed, try and find it
		if (!isset($formID)) {
			$sessionPost = session::get('POST');
			if (isset($sessionPost['MYSQL']) && isset($sessionPost['MYSQL']['__formID'])) {
				$formID = $sessionPost['MYSQL']['__formID'];
			} elseif (isset($_POST['MYSQL']['__formID'])) {
				$formID = $_POST['MYSQL']['__formID'];
			} else {
				return formProcessor::ERR_NO_ID;
			}
		}

		if (!isset(self::$formProcessorObjects[$formID])) {
			// Get the saved form
			$savedForm = self::getSavedForm($formID);
			if(!is_array($savedForm)) return $savedForm;

			// Save formBuilder and formType for east access
			$savedFormBuilder = $savedForm['formBuilder'];
			$savedFormType    = $savedForm['formType'];

			// Make sure we are linked to a backend db
			if (!sizeof($savedFormBuilder->dbOptions)) {
				errorHandle::newError(__METHOD__."() No database link defined for this form! (must process manually)", errorHandle::DEBUG);
				return FALSE;
			}

			// Create the form processor
			$formProcessor = new formProcessor($savedFormBuilder->dbOptions['table'], $savedFormBuilder->dbOptions['connection']);
			$formProcessor->formBuilder = $savedFormBuilder;

			// Set the processorType
			$formProcessor->setProcessorType($savedFormType);

			// Save editTable metadata (the primary field values) for processing stage
			if($savedFormType == self::TYPE_EDIT) $formProcessor->primaryFieldsValues = $savedFormBuilder->editTableRowData;

			// Add our fields to the form processor
			foreach ($savedFormBuilder->fields as $id => $field) {
				$formProcessor->addField($field);
			}

			// Save the formProcessor to the cache
			self::$formProcessorObjects[$formID] = $formProcessor;
		}

		return self::$formProcessorObjects[$formID];
	}

	/**
	 * [Factory] Create a new form object
	 *
	 * @param string $formName
	 * @param string $dbOptions
	 * @return bool|formBuilder
	 */
	public static function createForm($formName = NULL, $dbOptions = NULL){
		if (isnull($formName)) $formName = self::DEFAULT_FORM_NAME;
		$formNameCheck = trim(strtolower($formName));

		// Dupe checking
		if (in_array($formNameCheck, self::$formObjects)) {
			errorHandle::newError(__METHOD__."() Form already created with given name!", errorHandle::DEBUG);
			return FALSE;
		}

		// Create the form!
		self::$formObjects[$formNameCheck] = new self($formName);

		// link dbTableOptions if it's passed in
		if (!isnull($dbOptions) && !self::$formObjects[$formNameCheck]->linkToDatabase($dbOptions)) return FALSE;

		return self::$formObjects[$formNameCheck];
	}

	/**
	 * Link the form to a backend database table
	 *
	 * @param $dbOptions
	 * @return bool
	 */
	public function linkToDatabase($dbOptions){
		// If only a string is passed, make it the table name
		if (is_string($dbOptions)) $dbOptions = array('table' => $dbOptions);

		// Determine the db connection
		$dbOptions['connection'] = isset($dbOptions['connection']) ? db::get($dbOptions['connection']) : db::get('appDB');

		// Make sure that at least the table name is present
		if (!isset($dbOptions['table'])) {
			errorHandle::newError(__METHOD__."() You must pass at least a 'name' element with dbTableOptions!", errorHandle::DEBUG);
			return FALSE;
		}

		$this->dbOptions = $dbOptions;
		return TRUE;
	}

	/**
	 * EngineAPI Template tag callback
	 *
	 * @param array $matches
	 * @return string
	 */
	public static function templateMatches($matches){
		$attrPairs = attPairs($matches[1]);

		// Make sure we have a 'display' option
		if (!isset($attrPairs['display'])) $attrPairs['display'] = '';

		// If we're looking for assets, display them
		if($attrPairs['display'] == 'assets') return self::displayAssets();

		// Determine form name
		$formName = isset($attrPairs['name']) ? $attrPairs['name'] : self::DEFAULT_FORM_NAME;
		$formName = trim(strtolower($formName));

		// Locate the form, and if it's not defined return empty string
		if (isset(self::$formObjects[$formName])) {
			$form = self::$formObjects[$formName];
		} else {
			errorHandle::newError(__METHOD__."() Form '$formName' not defined", errorHandle::DEBUG);
			return '';
		}

		return $form->display($attrPairs['display'], $attrPairs);
	}

	/**
	 * Remove all fields, and reset back to initial state
	 */
	public function reset(){
		$this->fields = new formFields();
	}

	/**
	 * Returns an array of JS/CSS asset files needed by this form and its fields
	 *
	 * This array will follow the convention: assetName => assetFile
	 *
	 * @return array
	 */
	private function getAssets(){
		$assets = array();

		// Global assets
		$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'formEvents.js';

		// Template assets
		$path = $this->templateDir.DIRECTORY_SEPARATOR.$this->template;
		if(is_dir($path)){
			if(is_readable($path.DIRECTORY_SEPARATOR.'style.css')) $assets[] = $path.DIRECTORY_SEPARATOR.'style.css';
			if(is_readable($path.DIRECTORY_SEPARATOR.'script.js')) $assets[] = $path.DIRECTORY_SEPARATOR.'script.js';
		}

		// Get, and merge-in, all field assets
		foreach ($this->fields as $field) {
			$assets = array_merge($assets, (array)$field->getAssets());
		}

		// Return the final array
		return array_unique($assets);
	}

	/**
	 * Add a submit field if one doesn't exist already.
	 *
	 * @param string $buttonText
	 * @return bool Returns TRUE if a field was added, FALSE otherwise
	 */
	private function addFormSubmit($buttonText){
		foreach ($this->fields as $field) {
			if ($field->type == 'submit') return FALSE;
		}

		$submitField = array(
			'type'   => 'submit',
			'name'   => 'submit',
			'value'  => $buttonText,
			'showIn' => array(self::TYPE_INSERT, self::TYPE_UPDATE),
		);

		if($this->addField($submitField)){
			return TRUE;
		}else{
			errorHandle::newError(__METHOD__."() Failed to add submit field!", errorHandle::DEBUG);
			return FALSE;
		}
	}

	/**
	 * Add a delete field if one doesn't exist already.
	 *
	 * @param string $buttonText
	 * @return bool Returns TRUE if a field was added, FALSE otherwise
	 */
	private function addFormDelete($buttonText){
		foreach ($this->fields as $field) {
			if ($field->type == 'delete') return FALSE;
		}

		$deleteField = array(
			'type'   => 'delete',
			'name'   => 'delete',
			'value'  => $buttonText,
			'showIn' => array(self::TYPE_UPDATE),
		);

		if($this->addField($deleteField)){
			return TRUE;
		}else{
			errorHandle::newError(__METHOD__."() Failed to add delete field!", errorHandle::DEBUG);
			return FALSE;
		}
	}

	/**
	 * Make sure there are primary fields set
	 *
	 * (This is used on displayUpdateForm and displayEditTable for sanity checking)
	 *
	 * @return bool
	 */
	private function ensurePrimaryFieldsSet(){
		// Make sure there is at least 1 primary field set
		if(!$this->fields->countPrimary()){
			errorHandle::newError(__METHOD__."() No primary fields defined!", errorHandle::DEBUG);
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Generate a random formID for this form
	 * @return string
	 */
	private function generateFormID(){
		return md5(mt_rand());
	}

	/**
	 * Save the current form in the session
	 *
	 * @param string $formID The formID for this form
	 * @param string $formType The type of form being saved
	 */
	private function saveForm($formID, $formType){
		$sessionOptions = array('timeout' => enginevars::getInstance()->get('formBuilderTimeout', self::DEFAULT_FORM_TIMEOUT));
		$sessionData    = array(
			'formBuilder' => serialize($this),
			'formType'    => $formType,
		);
		session::set(self::SESSION_SAVED_FORMS_KEY.".$formID", $sessionData, $sessionOptions);
	}

	/**
	 * Returns the form template text to be used
	 * @param string $templateFile
	 * @param string $templateOverride
	 * @return string
	 */
	private function getTemplateText($templateFile, $templateOverride = NULL){
		// Get the current template
		$template = isset($templateOverride)
			? $templateOverride
			: $this->template;

		// If template is a file, use it
		if (is_file($template)) return file_get_contents($template);

		// If template is a dir, look inside it
		if (is_dir($template)) {
			$templateFile = $template.DIRECTORY_SEPARATOR.$templateFile;
			if (is_readable($templateFile)) return file_get_contents($templateFile);

			errorHandle::newError(__METHOD__."() No template file found at '".$templateFile."'", errorHandle::DEBUG);
			return '';
		}

		// Try appending the templateDir to tempalte and looking inside it
		if (is_dir($this->templateDir.DIRECTORY_SEPARATOR.$template)) {
			$templateBase = $this->templateDir.DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR;
			if (is_readable($templateBase.$templateFile)) return file_get_contents($templateBase.$templateFile);

			errorHandle::newError(__METHOD__."() No template file found at '".$templateBase.$templateFile."'", errorHandle::DEBUG);
			return '';
		}

		// All else fails, assume template is a blob
		return $template;
	}

	/**
	 * Main display method for the form
	 *
	 * @param string $display
	 * @param array  $options
	 * @return string
	 */
	public function display($display, $options=array()){
		$display = trim(strtolower($display));

		switch ($display) {
			case 'form':
				return $this->displayForm($options);

			case 'errors':
				return errorHandle::prettyPrint();

			default:
				// Last ditch, try display as a formType
				switch(self::getFormType($display)){
					case self::TYPE_INSERT:
						return $this->displayInsertForm($options);

					case self::TYPE_UPDATE:
						return $this->displayUpdateForm($options);

					case self::TYPE_EDIT:
						return $this->displayEditTable($options);

					default:
						errorHandle::newError(__METHOD__."() Unsupported display type '$display' for form '{$this->formName}'", errorHandle::DEBUG);
						return '';
				}
		}
	}

	/**
	 * Render all assets for all formBuilders
	 * @return string
	 */
	public static function displayAssets(){
		$assetFiles = array();
		foreach (self::$formObjects as $form) {
			$assetFiles = array_merge($assetFiles, $form->getAssets());
		}
		$assetFiles = array_unique($assetFiles);

		$jsAssetBlob  = '';
		$cssAssetBlob = '';
		foreach ($assetFiles as $file) {
			$ext = pathinfo($file, PATHINFO_EXTENSION);
			switch ($ext) {
				case 'less':
					// TODO
				case 'sass':
					// TODO
				case 'css':
					$cssAssetBlob .= minifyCSS($file);
					break;
				case 'js':
					// $jsAssetBlob .= minifyJS($file);  @TODO: Fix this function to not rely on ob_start() which doesn't work from engine tags
					$jsAssetBlob .= file_get_contents($file);
					break;
				default:
					errorHandle::newError(__METHOD__."() Unknown asset file type '$ext'. Ignoring file!", errorHandle::DEBUG);
					break;
			}
		}

		$output = "<!-- engine Instruction displayTemplateOff -->\n";
		if (!is_empty($jsAssetBlob)) $output .= "<script class='formBuilderScriptAssets'>".$jsAssetBlob."</script>";
		if (!is_empty($cssAssetBlob)) $output .= "<style class='formBuilderStyleAssets'>".$cssAssetBlob."</style>";
		return $output."<!-- engine Instruction displayTemplateOn -->\n";
	}

	/**
	 * Displays an insert or update form depending on if the primary fields have values
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayForm($options = array()){
		// If no primary fields set, display insertForm
		if(!sizeof($priFields = $this->fields->getPrimaryFields())) return $this->displayInsertForm($options);

		// If any primary field has no value, display insertForm
		foreach($priFields as $field){
			if(!$field->value) return $this->displayInsertForm($options);
		}

		// Else, display updateForm
		return $this->displayUpdateForm($options);
	}

	/**
	 * Displays an Insert Form using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayInsertForm($options = array()){
		// Add a submit button if one does not exist
		$submitAdded = $this->addFormSubmit($this->submitTextInsert);

		// Set default title if needed
		if (!isset($options['title'])) $options['title'] = $this->insertTitle;

		// Get the template text (overriding the template if needed)
		$templateFile = 'insertUpdate.html';
		$templateText = isset($options['template'])
			? $this->getTemplateText($templateFile, $options['template'])
			: $this->getTemplateText($templateFile);

		// Create the template object
		$template                = new formBuilderTemplate($this, $templateText);
		$template->formID        = $this->generateFormID();
		$template->formType      = self::TYPE_INSERT;
		$template->renderOptions = $options;

		// Apply any options
		if (isset($options['formAction'])) $template->formAttributes['action'] = $options['formAction'];

		// Render time!
		$output = $template->render();

		// Remove any submit button which we added (must be before form is saved)
		if($submitAdded) $this->fields->removeField('submit');

		// Save the form to the session
		$this->saveForm($template->formID, $template->formType);

		// Return the final output
		return $output;
	}

	/**
	 * Displays an Update Form using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayUpdateForm($options = array()){
		// Make sure there's primary fields set, and get a list of them
		if(!$this->ensurePrimaryFieldsSet()) return 'Misconfigured formBuilder!';
		$primaryFields = $this->fields->getPrimaryFields();

		// Make sure we have dbOptions
		if (!isset($this->dbOptions)) {
			errorHandle::newError(__METHOD__."() No database link! (see formBuilder::linkToDatabase())", errorHandle::DEBUG);
			return 'Misconfigured formBuilder!';
		}

		// Make sure each primary field has a value
		$primaryFieldsSQL = array();
		foreach ($primaryFields as $primaryField) {
			if (is_empty($primaryField->value)) {
				errorHandle::newError(__METHOD__."() No value for primary field '{$primaryField->name}' (unable to load current record)", errorHandle::DEBUG);
				return 'Misconfigured formBuilder!';
			}
			$primaryFieldsSQL[$primaryField->toSqlSnippet()] = $primaryField->value;
		}

		$db  = $this->dbOptions['connection'];
		$sql = sprintf('SELECT * FROM `%s` WHERE %s LIMIT 1',
			$db->escape($this->dbOptions['table']),
			implode(' AND ', array_keys($primaryFieldsSQL)));
		$stmt = $db->query($sql, array_values($primaryFieldsSQL));

		// Make sure we actually got a record back
		if (!$stmt->rowCount()) {
			errorHandle::newError(__METHOD__."() No record found! (SQL: $sql)", errorHandle::DEBUG);
			return 'No record found!';
		}

		/**
		 * Set the value of each field according to the database
		 * This is critical as other parts of formBuilder relies on these values being set BEFORE render time
		 */
		$row = $stmt->fetch();
		foreach ($row as $field => $value) {
			$this->fields->modifyField($field, 'value', $value);
		}

		// Add a submit button if one does not exist
		$submitAdded = $this->addFormSubmit($this->submitTextUpdate);

		// Add a delete button if one does not exist
		$deleteAdded = $this->addFormDelete($this->deleteTextUpdate);

		// Set default title if needed
		if (!isset($options['title'])) $options['title'] = $this->updateTitle;

		// Get the template text (overriding the template if needed)
		$templateFile = 'insertUpdate.html';
		$templateText = isset($options['template'])
			? $this->getTemplateText($templateFile, $options['template'])
			: $this->getTemplateText($templateFile);

		// Create the template object
		$template                = new formBuilderTemplate($this, $templateText);
		$template->formID        = $this->generateFormID();
		$template->formType      = self::TYPE_UPDATE;
		$template->renderOptions = $options;

		// Apply any options
		if (isset($options['formAction'])) $template->formAttributes['action'] = $options['formAction'];

		// Render time!
		$output = $template->render();

		// Remove any submit button which we added (must be before form is saved)
		// if($submitAdded) $this->fields->removeField('submit');

		// Remove any delete button which we added (must be before form is saved)
		// if($deleteAdded) $this->fields->removeField('delete');

		// Save the form to the session
		$this->saveForm($template->formID, $template->formType);

		// Return the final output
		return $output;
	}

	/**
	 * Displays an Edit Table using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayEditTable($options = array()){
		if(!$this->ensurePrimaryFieldsSet()) return 'Misconfigured formBuilder!';

		// Apply expandable default
		if(!isset($options['expandable'])) $options['expandable'] = $this->expandable;

		// Handle the case where expandable is TRUE, but all the fields are in the editStrip (negating the need to expand)
		if($options['expandable']){
			$editStripFieldCount = 0;
			foreach($this->fields as $field){
				if(in_array(self::TYPE_EDIT, $field->showIn) && $field->type != 'hidden') $editStripFieldCount++;
			}
			if($editStripFieldCount >= $this->fields->countVisible()) $options['expandable'] = FALSE;
		}

		// Add a submit button if one does not exist
		$submitAdded = $this->addFormSubmit($this->submitTextEdit);

		// Set default title if needed
		if (!isset($options['title'])) $options['title'] = $this->editTitle;

		// Get the template text (overriding the template if needed)
		$templateFile = 'editTable.html';
		$templateText = isset($options['template'])
			? $this->getTemplateText($templateFile, $options['template'])
			: $this->getTemplateText($templateFile);

		// Create the template object
		$template                = new formBuilderTemplate($this, $templateText);
		$template->formID        = $this->generateFormID();
		$template->formType      = self::TYPE_EDIT;
		$template->renderOptions = $options;

		// Apply any form attributes
		if (isset($options['formAction'])) $template->formAttributes['action'] = $options['formAction'];

		$ajaxURL = isset($options['ajaxHandlerURL']) ? $options['ajaxHandlerURL'] : $this->ajaxHandlerURL;
		if (!isnull($ajaxURL)) $template->formDataAttributes['ajax_url'] = $ajaxURL;

		$insertFormCallback = isset($options['insertFormCallback']) ? $options['insertFormCallback'] : $this->insertFormCallback;
		if (!isnull($insertFormCallback)) $template->formDataAttributes['insert_form_callback'] = $insertFormCallback;

		// Render time!
		$output = $template->render();

		// Remove any submit button which we added (must be before form is saved)
		if($submitAdded) $this->fields->removeField('submit');

		// Save the form to the session
		$this->saveForm($template->formID, $template->formType);

		// Return the final output
		return $output;
	}
}
