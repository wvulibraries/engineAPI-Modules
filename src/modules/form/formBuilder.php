<?php

class formBuilder extends formFields{
	const DEFAULT_FORM_NAME = '';

	/**
	 * @var self[] An array of all defined forms
	 */
	private static $formObjects = array();

	/**
	 * @var string The name given to this form
	 */
	private $formName;

	/**
	 * @var string Filepath to form templates
	 */
	public $templateDir;

	/**
	 * @var field[] Array of all primary fields
	 */
	private $primaryFields = array();

	/**
	 * @var array Array containing metadata linking this form to an underlying database table
	 */
	private $dbTableOptions;

	/**
	 * @var string The template to use for insertForm
	 */
	public $insertFormTemplate = 'default';

	/**
	 * @var string The template to use for editTable
	 */
	public $editTableTemplate = 'default';

	/**
	 * @var string Location of insertForm Ajax Callback
	 */
	public $insertFormURL;

	/**
	 * @var string Javascript function to process Ajax call
	 */
	public $insertFormCallback;

	/**
	 * Class constructor
	 *
	 * @param string $formName
	 */
	public function __construct($formName){
		$this->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$this->formName    = trim(strtolower($formName));
		templates::defTempPatterns('/\{formBuilder\s+(.+?)\}/', __CLASS__.'::templateMatches', $this);
	}

	/**
	 * Class destructor
	 */
	public function __destruct(){
		unset(self::$formObjects[$this->formName]);
	}

	/**
	 * [Magic Method] Read-only getter for our instance variables
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name){
		return isset($this->$name)
			? $this->$name
			: NULL;
	}

	/**
	 * Retruns the name of this formBuilder
	 * @return string
	 */
	public function getName(){
		return $this->formName;
	}

	/**
	 * Process a form submission
	 *
	 * @param string $formName
	 * @param array $data
	 * @return int
	 */
	public static function process($formName=NULL, $data=NULL){
		// Default to POST
		if (!isset($data)) $data = $_POST;

		// If no formName was passed, try and find it in the POST
		if (!isset($formName)) {
			if (!isset($data['__formName'])) return formProcessor::ERR_NO_NAME;
			$formName = $data['__formName'];
		}

		// Normalize the formName
		$formName = trim(strtolower($formName));

		// Make sure the formName is valid
		if (!isset(self::$formObjects[$formName])) return formProcessor::ERR_INVALID_NAME;

		// Create the form processor and GO BABY GO!
		$formBuilder   = self::$formObjects[$formName];
		$formProcessor = formProcessor::createProcessor();
		foreach ($formBuilder->fields as $field) {
			$formProcessor->addField($field);
		}
		return $formProcessor->process();
	}

	/**
	 * [Factory] Create a new form object
	 *
	 * @param string $formName
	 * @param string $dbTableOptions
	 * @return bool|formBuilder
	 */
	public static function createForm($formName = NULL, $dbTableOptions = NULL){
		if (isnull($formName)) $formName = self::DEFAULT_FORM_NAME;
		$formName = trim(strtolower($formName));

		// Dupe checking
		if (in_array($formName, self::$formObjects)) {
			errorHandle::newError(__METHOD__."() Form already created with given name!", errorHandle::DEBUG);
			return FALSE;
		}

		// Create the form!
		self::$formObjects[$formName] = new self($formName);

		// link dbTableOptions if it's passed in
		if (!isnull($dbTableOptions) && !self::$formObjects[$formName]->linkToDatabase($dbTableOptions)) return FALSE;

		return self::$formObjects[$formName];
	}

	/**
	 * [Factory] Creates formBuilderTemplate instance and returns it
	 * @return formBuilderTemplate
	 */
	private function createFormTemplate(){
		$template = new formBuilderTemplate($this);
		return $template;
	}

	/**
	 * Link the form to a backend database table
	 *
	 * @param $dbTableOptions
	 * @return bool
	 */
	public function linkToDatabase($dbTableOptions){
		// If only a string is passed, make it the table name
		if (is_string($dbTableOptions)) $dbTableOptions = array('table' => $dbTableOptions);
		// Make sure that at least the table name is present
		if (!isset($dbTableOptions['table'])) {
			errorHandle::newError(__METHOD__."() You must pass at least a 'name' element with dbTableOptions!", errorHandle::DEBUG);
			return FALSE;
		}
		$this->dbTableOptions = $dbTableOptions;

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
		if(isset(self::$formObjects[$formName])){
			$form = self::$formObjects[$formName];
		}else{
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
	 * Sets the given fields as primary fields for this form
	 *
	 * This will reset the current list, taking any passed in for the new set
	 * You can pass an unlimited number of fields in, so long as they're valid and have been added to the form
	 *
	 * @param string|fieldBuilder ...
	 * @return bool
	 */
	public function setPrimaryField(){
		// Reset the primary fields
		$this->primaryFields = array();

		// Get all the fields passed in and loop on each
		$fields = func_get_args();
		foreach ($fields as $field) {
			// If it's a string, try and convert it to one of our fields
			if (is_string($field)) {
				if (isnull($field = $this->getField($field))) {
					errorHandle::newError(__METHOD__."() Field not declared!", errorHandle::DEBUG);
					return FALSE;
				}
			}

			// Make sure we have a valid fieldBuilder object
			if (!($field instanceof fieldBuilder)) {
				errorHandle::newError(__METHOD__."() Not a valid fieldBuilder object!", errorHandle::DEBUG);
				return FALSE;
			}

			// Make sure the field has been added to the form
			if (!in_array($field, $this->fields)) {
				errorHandle::newError(__METHOD__."() Field not added to this form!", errorHandle::DEBUG);
				return FALSE;
			}

			// Save the new field to the list
			$this->primaryFields[] = $field->name;
		}
		return TRUE;
	}

	/**
	 * Returns an array of primary fields
	 *
	 * @return fieldBuilder[]
	 */
	public function getPrimaryFields(){
		$fields = array();
		foreach ($this->primaryFields as $field) {
			$fields[] = $this->getField($field);
		}
		return array_filter($fields);
	}

	/**
	 * Returns an array of primary field names
	 *
	 * @return array
	 */
	public function listPrimaryFields(){
		return $this->primaryFields;
	}

	/**
	 * Returns an array of JS/CSS asset files needed by this form and its fields
	 *
	 * This array will follow the convention: assetName => assetFile
	 *
	 * @return array
	 */
	private function getAssets(){
		// Form assets
		$assets = array(
			'formEvents' => __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'formEvents.js'
		);
		// Get, and merge-in, all field assets
		foreach ($this->fields as $field) {
			$assets = array_merge($assets, $field->getAssets());
		}
		// Return the final array
		return $assets;
	}

	/**
	 * Main display method for the form
	 *
	 * @param string $formType
	 * @param array $options
	 * @return string
	 */
	public function display($formType, $options){
		switch (strtolower($formType)) {
			case 'insertform':
				return $this->displayInsertForm($options);

			case 'edittable':
				return $this->displayEditTable($options);

			case 'js':
				$assetFiles = array();
				foreach (self::$formObjects as $form) {
					$assetFiles = array_merge($assetFiles, $form->getAssets());
				}

				$jsAssetBlob  = '';
				$cssAssetBlob = '';
				foreach ($assetFiles as $file) {
					$ext = pathinfo($file, PATHINFO_EXTENSION);
					switch ($ext) {
						case 'less':
						case 'sass':
						case 'css':
							$cssAssetBlob .= minifyCSS($file);
							break;
						case 'js':
							$jsAssetBlob .= minifyJS($file);
							break;
						default:
							errorHandle::newError(__METHOD__."() Unknown asset file type '$ext'. Ignoring file!", errorHandle::DEBUG);
							break;
					}
				}

				$output = '';
				if (!is_empty($jsAssetBlob))  $output .= "<script>".$jsAssetBlob."</script>";
				if (!is_empty($cssAssetBlob)) $output .= "<style>".$cssAssetBlob."</style>";
				return $output;

			default:
				errorHandle::newError(__METHOD__."() Unsupported display type '{$options['display']}' for form '$formName'", errorHandle::DEBUG);
				return '';
		}
	}

	/**
	 * Displays an Insert Form using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayInsertForm($options = array()){
		// Create the template object
		$template = $this->createFormTemplate();

		// Set the template
		$templatePath = isset($options['template']) ? $options['template'] : $this->insertFormTemplate;
		$template->loadTemplate($templatePath, 'insert');

		// Apply any options
		$template->formAction = isset($options['formAction']) ? $options['formAction'] : NULL;

		// Render time!
		return $template->render();
	}

	/**
	 * Displays an Edit Table using a given template
	 *
	 * @param array $options
	 * @return string
	 */
	public function displayEditTable($options = array()){
		// Create the template object
		$template = $this->createFormTemplate();

		// Set the template
		$templatePath = isset($options['template']) ? $options['template'] : $this->editTableTemplate;
		$template->loadTemplate($templatePath, 'edit');

		// Apply any options
		$template->formAction         = isset($options['formAction'])         ? $options['formAction']         : NULL;
		$template->insertFormURL      = isset($options['insertFormURL'])      ? $options['insertFormURL']      : NULL;
		$template->insertFormCallback = isset($options['insertFormCallback']) ? $options['insertFormCallback'] : NULL;

		// Render time!
		return $template->render();
	}
}
