<?php

class formBuilder extends formFields{
	const DEFAULT_FORM_NAME       = '';
	const DEFAULT_FORM_TIMEOUT    = 300;
	const SESSION_SAVED_FORMS_KEY = 'formBuilderForms';

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
	 * @var string The name given to this form
	 */
	private $formName;

	/**
	 * @var string The public title to apply to insert forms
	 */
	public $insertTitle;

	/**
	 * @var string The public tile to apply to update forms
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

		// Register errorHandle::prettyPrint callback and {form ...} template tags
		errorHandle::registerPrettyPrintCallback(array($this, 'prettyPrintFormErrors'));
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
	 * [Callback] Our custom callback for prettyPrint()
	 *
	 * @see errorHandle::prettyPrint()
	 * @param array  $errorStack
	 * @param string $type
	 * @return string
	 */
	public function prettyPrintFormErrors($errorStack, $type){
		// If there's no errorStack, return
		if (!sizeof($errorStack) || !isset($errorStack[$type])) return '';

		// Setup vars
		$engineErrors = array();
		$formErrors   = array();
		$errorStack   = (array)$errorStack[$type];

		// Loop through each error
		foreach ($errorStack as $v) {
			// If $v is a string, then it's a regular engine error
			if (is_string($v)) {
				$engineErrors[] = array(
					'msg'  => $v,
					'type' => $type,
				);
				continue;
			}

			// If $type is 'all' then this is a nested engine error
			if ($type == 'all') {
				$engineErrors[] = $v;
				continue;
			}

			// If $v is an array, and it has a key with our form name, then it's our form errors
			if (is_array($v) && isset($v[$this->formName])) {
				$formErrors = $v[$this->formName];
			}
		}

		$output = '';
		if (sizeof($engineErrors) || sizeof($formErrors)) {
			// Start building the errors <ul>
			$output .= '<ul class="errorPrettyPrint">';

			// Loop through all the engine errors
			foreach ($engineErrors as $engineError) {
				// Pull out the msg and type
				$msg  = $engineError['message'];
				$type = $engineError['type'];

				// Map the type to it's CSS class
				switch ($type) {
					case errorHandle::ERROR:
						$class = errorHandle::$uiClassError;
						break;
					case errorHandle::SUCCESS:
						$class = errorHandle::$uiClassSuccess;
						break;
					case errorHandle::WARNING:
						$class = errorHandle::$uiClassWarning;
						break;
					default:
						$class = '';
						break;
				}

				// Generate <li> HTML
				$output .= sprintf('<li><span class="%s">%s</span></li>', $class, htmlSanitize($msg, ENT_QUOTES, "UTF-8", FALSE));
			}

			// Loop through all the form errors and generate their <li> HTML
			foreach ($formErrors as $formError) {
				$output .= sprintf('<li><span class="%s">%s</span></li>', errorHandle::$uiClassError, htmlSanitize($formError, ENT_QUOTES, "UTF-8", FALSE));
			}

			// Finish the <ul> and return
			$output .= '</ul>';
		}

		return $output;
	}

	/**
	 * @inheritdoc
	 *
	 * @param array|fieldBuilder $field
	 * @return bool
	 */
	public function addField($field){
		$result = parent::addField($field);
		if ($result) {
			$field = array_peak($this->fields, 'end');
			if ($field->type == 'file') {
				$this->formEncoding = '';
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
			// If this isn't an AJAX request, we don't care
			if (!isAJAX()) return NULL;
			if (isset($_GET['MYSQL']) && sizeof($_GET['MYSQL'])) {
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
							$savedForm->modifyField($field,'value',$value);
						}

						// Return the form
						die(json_encode(array(
							'success' => TRUE,
							'form'    => $savedForm->display($formType, $displayOptions)
						)));
				}
			} elseif (isset($_POST['MYSQL']) && sizeof($_POST['MYSQL'])) {
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
		/*
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
		*/

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
		// If there's no POST, return
		if(!sizeof($_POST) && !session::has('POST')) return NULL;

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

			// Set the processorType
			$formProcessor->setProcessorType($savedFormType);

			// Set the primary fields
			call_user_func_array(array($formProcessor, 'addPrimaryFields'), $savedFormBuilder->listPrimaryFields());
			if($savedFormType == 'editTable') $formProcessor->primaryFieldsValues = $savedFormBuilder->editTableRowData;

			// Add our fields to the form processor
			foreach ($savedFormBuilder->fields as $field) {
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

		if (!isset($attrPairs['display'])) $attrPairs['display'] = '';
		return $form->display($attrPairs['display'], $attrPairs);
	}

	/**
	 * Remove all fields, and reset back to initial state
	 */
	public function reset(){
		$this->fields      = array();
		$this->fieldLabels = array();
		$this->fieldIDs    = array();
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
		$assets[] = 'http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js';
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
			'type'            => 'submit',
			'name'            => 'submit',
			'value'           => $buttonText,
			'showInEditStrip' => FALSE,
		);

		if($this->addField($submitField)){
			return TRUE;
		}else{
			errorHandle::newError(__METHOD__."() Failed to add submit field!", errorHandle::DEBUG);
			return FALSE;
		}
	}

	private function ensurePrimaryFieldsSet(){
		// Make sure there is at least 1 primary field set
		if(!sizeof($this->primaryFields)){
			errorHandle::newError(__METHOD__."() No primary fields set! (see formBuilder::addPrimaryFields())", errorHandle::DEBUG);
			return FALSE;
		}

		// Make sure that all primary fields have a full field definition
		$missingFieldDefinitions = array_diff($this->primaryFields, array_keys($this->fields));
		if(sizeof($missingFieldDefinitions)){
			errorHandle::newError(__METHOD__."() Primary field(s) ".implode(',', $missingFieldDefinitions)." missing their definitions!", errorHandle::DEBUG);
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

			case 'insert':
			case 'insertform':
				return $this->displayInsertForm($options);

			case 'update':
			case 'updateform':
				return $this->displayUpdateForm($options);

			case 'edit':
			case 'edittable':
				return $this->displayEditTable($options);

			case 'expandable':
			case 'expandableedit':
			case 'expandabletable':
			case 'expandableedittable':
				if(!$this->ensurePrimaryFieldsSet()) return 'Misconfigured formBuilder!';
				return $this->displayExpandableEditTable($options);

			case 'assets':
				$assetFiles = $this->getAssets();
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
							// $jsAssetBlob .= minifyJS($file);
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

			case 'errors':
				return errorHandle::prettyPrint();

			default:
				errorHandle::newError(__METHOD__."() Unsupported display type '$display' for form '{$this->formName}'", errorHandle::DEBUG);
				return '';
		}
	}

	/**
	 * Displays an insert or update form depending on if the primary fields have values
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayForm($options = array()){
		// If no primary fields set, display insertForm
		if(!sizeof($priFields = $this->getPrimaryFields())) return $this->displayInsertForm($options);

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
		$template = new formBuilderTemplate($this, $templateText);
		$template->formID = $this->generateFormID();
		$template->renderOptions = $options;

		// Apply any options
		if (isset($options['formAction'])) $template->formAttributes['action'] = $options['formAction'];

		// Render time!
		$output = $template->render();

		// Remove any submit button which we added (must be before form is saved)
		if($submitAdded) $this->removeField('submit');

		// Save the form to the session
		$this->saveForm($template->formID, 'insertForm');

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
		$primaryFields = $this->getPrimaryFields();

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
//			implode('`,`', $this->listFields()),
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
			$this->modifyField($field, 'value', $value);
		}

		// Add a submit button if one does not exist
		$submitAdded = $this->addFormSubmit($this->submitTextUpdate);

		// Set default title if needed
		if (!isset($options['title'])) $options['title'] = $this->updateTitle;

		// Get the template text (overriding the template if needed)
		$templateFile = 'insertUpdate.html';
		$templateText = isset($options['template'])
			? $this->getTemplateText($templateFile, $options['template'])
			: $this->getTemplateText($templateFile);

		// Create the template object
		$template = new formBuilderTemplate($this, $templateText);
		$template->formID = $this->generateFormID();
		$template->renderOptions = $options;

		// Apply any options
		if (isset($options['formAction'])) $template->formAttributes['action'] = $options['formAction'];

		// Render time!
		$output = $template->render();

		// Remove any submit button which we added (must be before form is saved)
		if($submitAdded) $this->removeField('submit');

		// Save the form to the session
		$this->saveForm($template->formID, 'updateForm');

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
				if($field->showInEditStrip) $editStripFieldCount++;
			}
			if($editStripFieldCount == sizeof($this->fields)) $options['expandable'] = FALSE;
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
		$template = new formBuilderTemplate($this, $templateText);
		$template->formID = $this->generateFormID();
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
		if($submitAdded) $this->removeField('submit');

		// Save the form to the session
		$this->saveForm($template->formID, 'editTable');

		// Return the final output
		return $output;
	}

	/**
	 * Displays an Expandable Edit Table using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayExpandableEditTable($options = array()) {
		$options['expandable'] = TRUE;
		return $this->displayEditTable($options);
	}
}
