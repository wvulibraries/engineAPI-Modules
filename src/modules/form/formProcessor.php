<?php

class formProcessor extends formFields{
	const ERR_OK         = 0;
	const ERR_NO_POST    = 1;
	const ERR_NO_ID      = 2;
	const ERR_INVALID_ID = 3;
	const ERR_VALIDATION = 4;
	const ERR_SYSTEM     = 5;
	const ERR_TYPE       = 6;

	const TYPE_INSERT    = 1;
	const TYPE_UPDATE    = 2;
	const TYPE_EDIT      = 3;

	public static $errorMessages = array(
		self::ERR_NO_POST    => 'No data received',
		self::ERR_NO_ID      => 'No formID received',
		self::ERR_INVALID_ID => 'Invalid ID received',
		self::ERR_VALIDATION => 'Validation error',
		self::ERR_SYSTEM     => 'Internal system error',
		self::ERR_TYPE       => 'Invalid formType',
	);

	/**
	 * @var array The data to process
	 */
	private $processData;

	/**
	 * @var int The internal type of form to be processed
	 */
	private $processorType;

	/**
	 * @var dbDriver The database connection
	 */
	private $db;

	/**
	 * @var string The database table
	 */
	private $dbTable;

	/**
	 * @var array Array of primary key values (used in self::__processEdit())
	 */
	public $primaryFieldsValues;

	public function __construct($dbTableName, $dbConnection=NULL){
		$this->db = ($dbConnection instanceof dbDriver)
			? $dbConnection
			: db::get('appDB');
		$this->dbTable = $this->db->escape($dbTableName);
	}

	public function setProcessorType($type){
		switch(trim(strtolower($type))) {
			case self::TYPE_INSERT:
			case 'insert':
			case 'insertform':
				$this->processorType = self::TYPE_INSERT;
				break;
			case self::TYPE_UPDATE:
			case 'update':
			case 'updateform':
				$this->processorType = self::TYPE_UPDATE;
				break;
			case self::TYPE_EDIT:
			case 'edit':
			case 'edittable':
				$this->processorType = self::TYPE_EDIT;
				break;
			default:
				errorHandle::newError(__METHOD__."() Invalid processorType! '$type'", errorHandle::DEBUG);
				return FALSE;
		}
		return TRUE;
	}

	public function processPost(){
		if (!$this->processorType) return self::ERR_TYPE;

		if(!session::has('POST')){
			if(sizeof($_POST)){
				// Save the POST in the session and redirect back to the same URL (this time w/o POST data)
				session::set('POST', $_POST, TRUE);
				http::redirect($_SERVER['REQUEST_URI'], 303, TRUE);
			}else{
				errorHandle::newError(__METHOD__."() Cannot find saved POST data!", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
			}
		}

		/*
		 * Extract the RAW data from _POST and pass it to process() for processing
		 *
		 * We use RAW here to avoid double-escaping when we process the data in process()
		 * This may happen because the developer will use process() to handle his own raw data
		 * Since the database module uses prepared statements, manually escaping the POST data is not necessary
		 */
		$post = session::get('POST');
		session::destroy('POST');
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);
		return $this->process($post['RAW']);
	}

	public function process($data){
		// Save the data to our processData var
		$this->processData = $data;

		switch($this->processorType){
			case self::TYPE_INSERT:
				$result = $this->__processInsert();
				break;
			case self::TYPE_UPDATE:
				$result = $this->__processUpdate();
				break;
			case self::TYPE_EDIT:
				$result = $this->__processEdit();
				break;
			default:
				errorHandle::newError(__METHOD__."() Invalid formType! (engineAPI bug)", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
		}
		if($result === self::ERR_OK) errorHandle::successMsg('Form submission successful!');
		return $result;
	}

	private function __processValidation($data){
		// Validation
		$isValid   = TRUE;
		$validator = validate::getInstance();
		foreach($this->fields as $field){
			// If no validation set, skip
			if(isnull($field->validate)) continue;

			// If no data for this field, skip
			if(!isset($data[ $field->name ])) continue;

			// Try and validate the data
			$fieldData = $data[ $field->name ];
			$result = method_exists($validator, $field->validate)
				? call_user_func(array($validator, $field->validate), $fieldData)
				: $validator->regexp($field->validate, $fieldData);

			// Did an error occur? (like a bad regex pattern)
			if($result === NULL) errorHandle::newError(__METHOD__."() Error occurred during validation for field '{$field->name}'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);

			// Did validation fail?
			if(!$result){
				$isValid = FALSE;
				errorHandle::errorMsg($validator->getErrorMessage($field->validate, $fieldData));
			}
		}
		return $isValid;
	}

	private function __processInsert(){
		// Process field validation rules
		if(!$this->__processValidation($this->processData)) return self::ERR_VALIDATION;

		$sqlFields = array();
		foreach($this->fields as $field){
			// Skip disabled fields
			if($field->disabled) continue;

			// Revert read-only fields to their original state
			if($field->readonly) $this->processData[ $field->name ] = $field->renderedValue;

			// Save the field for insertion
			$sqlFields[ $field->name ] = $this->processData[ $field->name ];
		}

		// Build the SQL
		$sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
			$this->dbTable,
			implode('`,`',array_keys($sqlFields)),
			implode(',',array_fill(0,sizeof($sqlFields),'?')));

		// Execute the SQL and check for errors
		$stmt = $this->db->query($sql, $sqlFields);
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! ({$stmt->errorCode()}:{$stmt->errorMsg()})", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}

		// All's well
		return self::ERR_OK;
	}

	private function __processUpdate($data=NULL){
		if(isnull($data)) $data = $this->processData;

		// Process field validation rules
		if(!$this->__processValidation($data)) return self::ERR_VALIDATION;

		$sqlFields = array();
		foreach($this->fields as $field){
			// Skip special fields
			if(in_array($field->type, array('submit','reset','button'))) continue;
			// Skip disabled fields
			if($field->disabled) continue;
			// Revert read-only fields to their original state
			if($field->readonly) $data[ $field->name ] = $field->renderedValue;
			// Save the field for insertion
			$sqlFields[ $field->toSqlSnippet() ] = isset($data[ $field->name ])
				? $data[ $field->name ]
				: $field->value;
		}

		$primaryFieldsSQL = array();
		foreach ($this->getPrimaryFields() as $primaryField) {
			$primaryFieldsSQL[$primaryField->toSqlSnippet()] = $primaryField->value;
		}

		$sql = sprintf('UPDATE `%s` SET %s WHERE %s LIMIT 1',
			$this->dbTable,
			implode(',', array_keys($sqlFields)),
			implode(' AND ', array_keys($primaryFieldsSQL)));

		$stmt = $this->db->query($sql, array_merge(array_values($sqlFields), array_values($primaryFieldsSQL)));
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! ({$stmt->errorCode()}:{$stmt->errorMsg()})", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}

		return self::ERR_OK;
	}

	private function __processEdit(){
		$errorCode = self::ERR_OK;

		// Convert field-loop to row-loop
		$rows = array();
		foreach($this->processData as $fieldName => $fieldRows){
			foreach((array)$fieldRows as $rowID => $rowData){
				$rows[$rowID][$fieldName] = $rowData;
			}
		}

		// Validate the row
		foreach($rows as $rowID => $fields){
			// Skip system fields
			if($rowID == 0) continue;

			// Add value to primary fields
			foreach($this->listPrimaryFields() as $field){
				$this->modifyField($field,'value',$this->primaryFieldsValues[$rowID][ $field ]);
			}

			// Update the row and handle the result
			switch($this->__processUpdate($fields)){
				// Skip this row if it fails validation
				case self::ERR_VALIDATION:
					$errorCode = self::ERR_VALIDATION;
					continue;
				// Return if we have a system error
				case self::ERR_SYSTEM:
					return self::ERR_SYSTEM;
			}
		}

		return $errorCode;
	}
}