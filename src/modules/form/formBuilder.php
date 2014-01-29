<?php

class formBuilder implements Countable{
	const DEFAULT_ORDER     = '_z_';
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
	 * @var string The action for the generated <form> tag
	 */
	private $formAction;

	/**
	 * @var string Filepath to form templates
	 */
	public $templateDir;

	/**
	 * @var fieldBuilder[] The fields themselves
	 */
	private $fields = array();

	/**
	 * @var array Index of field labels (to maintain uniqueness)
	 */
	private $fieldLabels = array();

	/**
	 * @var array Index of all field IDs (to maintain uniqueness)
	 */
	private $fieldIDs = array();

	/**
	 * @var string[] List of fields which have been rendered (used for de-duping during render)
	 */
	private $fieldsRendered = array();

	/**
	 * @var field[] Array of all primary fields
	 */
	private $primaryFields = array();

	/**
	 * @var array Store the ordering of the fields
	 */
	private $fieldOrdering = array();

	/**
	 * @var array Array containing metadata linking this form to an underlying database table
	 */
	private $dbTableOptions;

	/**
	 * @var int Counts the number of rows
	 */
	private $counterRows = 0;

	/**
	 * @var int Counts the number of fields
	 */
	private $counterFields = 0;

	/**
	 * Class constructor
	 *
	 * @param string $formName
	 */
	function __construct($formName){
		$this->templateDir = __DIR__.DIRECTORY_SEPARATOR.'formTemplates'.DIRECTORY_SEPARATOR;
		$this->formName    = trim(strtolower($formName));
		templates::defTempPatterns('/\{formBuilder\s+(.+?)\}/', __CLASS__.'::templateMatches', $this);
	}

	/**
	 * Class destructor
	 */
	function __destruct(){
		unset(self::$formObjects[$this->formName]);
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
	 * @TODO
	 */
	public static function templateMatches($matches){
		$attPairs = attPairs($matches[1]);

		// Determine form name
		$formName = isset($attPairs['name']) ? $attPairs['name'] : self::DEFAULT_FORM_NAME;
		$formName = trim(strtolower($formName));

		// Locate the form, and if it's not defined return empty string
		$form = isset(self::$formObjects[$formName]) ? self::$formObjects[$formName] : NULL;
		if (!isset($form)) {
			errorHandle::newError(__METHOD__."() Form '$formName' not defined", errorHandle::DEBUG);
			return '';
		}

		if (!isset($attPairs['display'])) $attPairs['display'] = '';
		switch ($attPairs['display']) {
			case "insertForm":
				return $form->displayInsertForm();
			case "editTable":
				return $form->displayEditTable();
			default:
				errorHandle::newError(__METHOD__."() Unsupported display type '{$attPairs['display']}' for form '$formName'", errorHandle::DEBUG);
				return '';
		}
	}

	/**
	 * Returns the number of fields
	 *
	 * @return int
	 */
	public function count(){
		return sizeof($this->fields);
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
	 * Add a field
	 *
	 * @param array|fieldBuilder $field
	 * @return bool
	 */
	public function addField($field){
		// If we got an array, make it a fieldBuilder
		if (is_array($field)) $field = fieldBuilder::createField($field);

		// Make sure we're working with a fieldBuilder
		if (!($field instanceof fieldBuilder)) return FALSE;

		// Make sure the field name is unique
		if (isset($this->fields[$field->name])) return FALSE;

		// If there's a label, make sure it's unique
		if (!isempty($field->label) && in_array($field->label, $this->fieldLabels)) return FALSE;

		// If there's a field ID, make sure it's unique
		if (!isempty($field->fieldID) && in_array($field->fieldID, $this->fieldIDs)) return FALSE;

		// If we're here, then all is well. Save the field and return
		if (!isempty($field->fieldID)) $this->fieldIDs[$field->name] = $field->fieldID;
		if (!isempty($field->label)) $this->fieldLabels[$field->name] = $field->label;
		$this->fields[$field->name] = $field;

		// Record the sort-order for this field
		$order                         = !isempty($field->order) ? $field->order : self::DEFAULT_ORDER;
		$this->fieldOrdering[$order][] = $field;

		return TRUE;
	}

	/**
	 * Remove a field
	 *
	 * @param string $fieldName
	 * @return bool
	 */
	public function removeField($fieldName){
		if (isset($this->fields[$fieldName])) {
			unset($this->fields[$fieldName]);
			unset($this->fieldLabels[$fieldName]);
			unset($this->fieldIDs[$fieldName]);

			return TRUE;
		} else {
			return FALSE;
		}
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
	 * Modify a specific field option on the given field
	 *
	 * @param fieldBuilder|string $fieldName
	 * @param string              $option
	 * @param mixed               $value
	 * @return bool
	 */
	public function modifyField($fieldName, $option, $value){
		if ($fieldName instanceof fieldBuilder) {
			$fieldName->$option = $value;

			return TRUE;
		} elseif (is_string($fieldName) && isset($this->fields[$fieldName])) {
			$this->fields[$fieldName]->$option = $value;

			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Calls modifyField() on each field
	 *
	 * @see self::modifyField()
	 * @param string $option
	 * @param mixed  $value
	 * @return bool
	 */
	public function modifyAllFields($option, $value){
		foreach ($this->fields as $field) {
			if (!$this->modifyField($field, $option, $value)) return FALSE;
		}

		return TRUE;
	}

	/**
	 * Return TRUE if the given field name has been defined
	 *
	 * @param string $name
	 * @return bool
	 */
	public function fieldExists($name){
		return isset($this->fields[$name]);
	}

	/**
	 * Returns the requested field or NULL if no field defined
	 *
	 * @param $name
	 * @return fieldBuilder|NULL
	 */
	public function getField($name){
		return isset($this->fields[$name])
			? $this->fields[$name]
			: NULL;
	}

	/**
	 * Returns an array of all defined field names
	 *
	 * @param bool $editStrip
	 * @return array
	 */
	public function listFields($editStrip = NULL){
		// We need to map the keys to string for consistency
		$keys = array_keys($this->getSortedFields($editStrip));
		$keys = array_map(function ($n){
			return (string)$n;
		}, $keys);

		return $keys;
	}

	/**
	 * Returns a clean, and fully sorted, array of fields in the order they should appear
	 *
	 * @param bool $editStrip
	 * @return array
	 */
	private function getSortedFields($editStrip = NULL){
		// Get local copied of the fieldOrderings
		$fieldOrdering = $this->fieldOrdering;

		// Pull out the unordered fields
		$unorderedFields = isset($fieldOrdering[self::DEFAULT_ORDER]) ? $fieldOrdering[self::DEFAULT_ORDER] : array();
		unset($fieldOrdering[self::DEFAULT_ORDER]);

		// Sort the ordered fields by their keys
		ksort($fieldOrdering);

		// Re-add the unordered fields to the end
		$fieldOrdering[self::DEFAULT_ORDER] = $unorderedFields;

		// Build up the master fields array
		$sortedFields = array();
		foreach ($fieldOrdering as $fieldGroup) {
			foreach ($fieldGroup as $field) {
				// Skip fields if they don't match $editStrip (null shows all)
				if (!isnull($editStrip)) {
					if ($editStrip && !$field->showInEditStrip) continue;
					if (!$editStrip && $field->showInEditStrip) continue;
				}

				$sortedFields[$field->name] = $field;
			}
		}

		// Return the final, sorted, array of fields
		return $sortedFields;
	}

	/**
	 * Locate and return the file contents of the requested template
	 *
	 * If $path and $type point to a valid file on the file system, then load and return it
	 * Else, assume $path contains the template text itself (it's a blob)
	 *
	 * @param string $path
	 * @param string $type
	 * @return string
	 */
	private function loadTemplate($path, $type){
		// This anonymous function allows $path to accept a file or directory
		$routeToFile = function ($path, $type){
			// If path is a full filepath, just use what we got
			if (is_file($path)) return file_get_contents($path);

			// If path isn't a directory, then what the heck is it?
			if (!is_dir($path)) return '';

			/* Try and locate the file based on $type
			 * $type may be the filename itself (ie foo.txt)
			 * $type may be the basename of the file in which case we look for files with appropriate extensions (.txt .htm(l) .php)
			 */
			$basePath = $path.DIRECTORY_SEPARATOR.$type;
			if (file_exists($basePath)) return file_get_contents($basePath);
			foreach (array('txt', 'html', 'htm', 'php') as $ext) {
				if (file_exists("$basePath.$ext")) return file_get_contents("$basePath.$ext");
			}

			// Well, we're out of ideas
			return '';
		};

		// Try and load a custom/global template (a full file path from the developer)
		if (file_exists($path)) {
			$output = $routeToFile($path, $type);
			if (!isempty($output)) return $output;
		}

		// Try and load one of our distribution templates (the ones next to this module)
		if (file_exists($this->templateDir.$path)) {
			$output = $routeToFile($this->templateDir.$path, $type);
			if (!isempty($output)) return $output;
		}

		// All else fails: load the default
		return $path;

	}

	/**
	 * Displays an Insert Form using a given template
	 *
	 * @param string $template
	 * @param string $formAction
	 * @return string
	 * @todo Needs testing
	 */
	public function displayInsertForm($template = NULL, $formAction = NULL){
		if (isnull($template)) $template = 'default';
		$this->formAction = $formAction;
		$template         = $this->processTemplate($this->loadTemplate($template, 'insert'));
		$this->formAction = NULL;

		return $template;
	}

	/**
	 * Displays an Edit Table using a given template
	 *
	 * @param string $template
	 * @param string $formAction
	 * @return string
	 * @todo Needs testing
	 */
	public function displayEditTable($template = NULL, $formAction = NULL){
		if (isnull($template)) $template = 'default';
		$this->formAction = $formAction;
		$template         = $this->processTemplate($this->loadTemplate($template, 'edit'));
		$this->formAction = NULL;

		return $template;
	}

	/**
	 * Process all the form template tags
	 *
	 * @param string $templateText
	 * @return string
	 */
	public function processTemplate($templateText){
		// Reset the list of rendered fields
		$this->fieldsRendered = array();
		// Process {fieldsLoop}
		$templateText = preg_replace_callback('|{fieldsLoop(.*?)}(.+?){/fieldsLoop}|i', array($this, '__processFieldLoop'), $templateText);
		// Process {rowLoop}
		$templateText = preg_replace_callback('|{rowLoop(.*?)}(.+?){/rowLoop}|i', array($this, '__processRowLoop'), $templateText);
		// Process general tags
		$templateText = preg_replace_callback('|{([/\w]+)\s?(.*?)}|', array($this, '__processTemplateGeneral'), $templateText);

		return $templateText;
	}

	/**
	 * [PREG Callback] Process all {fieldLoop}'s
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __processFieldLoop($matches){
		$output     = '';
		$options    = attPairs($matches[1]);
		$block      = $matches[2];
		$list       = isset($options['list'])       ? explode(',', $options['list'])   : $this->listFields();
		$editStrip  = isset($options['editStrip'])  ? str2bool($options['editStrip'])  : NULL;
		$showHidden = isset($options['showHidden']) ? str2bool($options['showHidden']) : TRUE;

		if ($showHidden || $showHidden === NULL) {
			foreach ($this->fields as $field) {
				// Skip the field if it's not in the list
				if (!in_array($field->name, $list)) continue;
				// Skip fields that have already been rendered
				if (in_array($field->name, $this->fieldsRendered)) continue;
				// We only care if this is a hidden field
				if ($field->type == 'hidden') {
					$output .= $field->render();
					$this->fieldsRendered[] = $field->name;
				}
			}
		}

		foreach ($this->getSortedFields($editStrip) as $field) {
			// Skip fields that have already been rendered
			if (in_array($field->name, $this->fieldsRendered)) continue;

			// Skip any hidden fields, we've already processed them
			if ($field->type == 'hidden') continue;

			// Skip the field if it's not in the list
			if (!in_array($field->name, $list)) continue;

			// Replace any unnamed field with a named version for this field
			$output .= preg_replace('/{field(?!.*name=".+".*).*}/', '{field $1 name="'.$field->name.'"}', $block);
		}

		return $output;
	}

	/**
	 * [PREG Callback] Process all {rowLoop}'s
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __processRowLoop($matches){
		$output  = '';
		$options = attPairs($matches[1]);
		$block   = $matches[2];

		// Extract db table stuff into vars
		$dbConnection = isset($this->dbTableOptions['dbConnection']) ? $this->dbTableOptions['dbConnection'] : 'appDB';
		$order        = isset($this->dbTableOptions['order']) ? $this->dbTableOptions['order'] : NULL;
		$where        = isset($this->dbTableOptions['where']) ? $this->dbTableOptions['where'] : NULL;
		$limit        = isset($this->dbTableOptions['limit']) ? $this->dbTableOptions['limit'] : NULL;
		$table        = $this->dbTableOptions['table'];

		// Sanity check
		if (isnull($table)) {
			errorHandle::newError(__METHOD__."() No table defined in dbTableOptions! (Did you forget to call linkToDatabase()?)", errorHandle::DEBUG);
			return '';
		}

		// Get the db connection we'll be talking to
		if (!$db = db::getInstance()->$dbConnection) {
			errorHandle::newError(__METHOD__."() Database connection failed to establish", errorHandle::DEBUG);
			return '';
		}

		// Build the SQL
		$sql = sprintf('SELECT * FROM `%s`', $db->escape($table));
		if (!isempty($where)) $sql .= " WHERE $where";
		if (!isempty($order)) $sql .= " ORDER BY $order";
		if (!isempty($limit)) $sql .= " LIMIT $limit";

		// Run the SQL
		$sqlResult = $db->query($sql);

		// Save the number of rows
		$this->counterRows = $sqlResult->rowCount();

		// Figure out the fields we need to loop on (and save the number of fields)
		preg_match_all('/{field.*?name="(\w+)".*?}/', $block, $matches);
		$templateFields      = array_intersect($sqlResult->fieldNames(), $matches[1]);
		$this->counterFields = sizeof($templateFields);

		// Loop over each row, transforming the block into a compiled block
		while ($row = $sqlResult->fetch()) {
			$rowBlock = $block;
			foreach ($row as $field => $value) {
				if (!in_array($field, $templateFields)) continue;
				$rowBlock = preg_replace('/{field\s+((?=.*name="'.preg_quote($field).'".*)(?!.*value=".+".*).*)}/', '{field $1 value="'.$value.'"}', $rowBlock);
				$rowBlock = preg_replace('/{field\s+((?=.*name="'.preg_quote($field).'".*)(?=.*display="value".*).*)}/', $value, $rowBlock);
			}
			$output .= $rowBlock;
		}

		// Replace any field or row count tags inside our block
		$output = str_replace('{rowCount}', $this->counterRows, $output);
		$output = str_replace('{fieldCount}', $this->counterFields, $output);

		// Return the compiled block
		return $output;
	}

	/**
	 * [PREG Callback] Process general template tags
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __processTemplateGeneral($matches){
		$tmplTag   = trim($matches[1]);
		$attrPairs = attPairs($matches[2]);
		switch (strtolower($tmplTag)) {
			case 'formtitle':
				return $this->formName;

			case 'form':
				$output = '';
				$showHidden = isset($attrPairs['hidden']) ? str2bool($attrPairs['hidden']) : FALSE;
				unset($attrPairs['hidden']);

				// Build any extra attributes for the <form> tag
				$attrs = array();
				foreach ($attrPairs as $key => $value) {
					$attrs[] = $key.'="'.$value.'"';
				}
				// Build the <form> tag
				$output .= sprintf('<form method="post"%s %s>',
					(isnull($this->formAction) ? '' : ' action="'.$this->formAction.'"'),
					implode(' ', $attrs));

				// Add any hidden fields (if needed)
				if($showHidden){
					foreach($this->fields as $field){
						if (in_array($field->name, $this->fieldsRendered)) continue;
						if($field->type == 'hidden') {
							$output .= $field->render();
							$this->fieldsRendered[] = $field->name;
						}
					}
				}

				// Return the result
				return $output;

			case '/form':
				return '</form>';

			case 'fields':
				$output  = '';
				$display = isset($attrPairs['display'])
					? trim(strtolower($attrPairs['display']))
					: 'full';

				foreach ($this->fields as $field) {
					if (in_array($field->name, $this->fieldsRendered)) continue;
					$this->fieldsRendered[] = $field->name;

					switch ($display) {
						case 'full':
							$output .= $field->render();
							break;
						case 'fields':
							$output .= $field->renderField();
							break;
						case 'labels':
							$output .= $field->renderLabel();
							break;
						case 'hidden':
							if($field->type == 'hidden') $output .= $field->render();
							break;
						default:
							errorHandle::newError(__METHOD__."() Invalid 'display' for {fields}! (only full|fields|labels valid)", errorHandle::DEBUG);
							return '';
					}
				}

				return $output;

			case 'field':
				if (!isset($attrPairs['name'])) {
					errorHandle::newError(__METHOD__."() 'name' is required for {field} tags", errorHandle::DEBUG);
					return '';
				}

				$field = $this->getField($attrPairs['name']);
				if (isnull($field)) {
					errorHandle::newError(__METHOD__."() No field defined for '{$attrPairs['name']}'!", errorHandle::DEBUG);
					return '';
				}

				$display  = isset($attrPairs['display'])
					? trim(strtolower($attrPairs['display']))
					: 'full';
				$template = isset($attrPairs['template'])
					? trim(strtolower($attrPairs['template']))
					: NULL;

				switch ($display) {
					case 'full':
						return $field->render($template);
					case 'field':
						return $field->renderField();
					case 'label':
						return $field->renderLabel();
					default:
						errorHandle::newError(__METHOD__."() Invalid 'display' for field '{$attrPairs['name']}'! (only full|field|label valid)", errorHandle::DEBUG);
						return '';
				}

			case 'fieldset':
				$legend = isset($attrPairs['legend']) && !isempty($attrPairs['legend'])
					? '<legend>'.$attrPairs['legend'].'</legend>'
					: '';

				return '<fieldset>'.$legend;

			case '/fieldset':
				return '</fieldset>';

			case 'rowcount':
				return (string)$this->counterRows;

			case 'fieldcount':
				return (string)$this->counterFields;

			// By default we need to return the whole tag because it must not be one of our tags.
			default:
				return $matches[0];
		}
	}
}