<?php

class formProcessor extends formFields{
	const ERR_OK         = 0;
	const ERR_NO_POST    = 1;
	const ERR_NO_ID      = 2;
	const ERR_INVALID_ID = 3;
	const ERR_CSRF_CHECK = 4;
	const ERR_VALIDATION = 5;
	const ERR_SYSTEM     = 6;
	const ERR_TYPE       = 7;
	const TYPE_INSERT    = 1;
	const TYPE_UPDATE    = 2;
	const TYPE_EDIT      = 3;

	/**
	 * @var array The data to process
	 */
	private $processData;

	/**
	 * @var int The internal type of form to be processed
	 */
	private $processorType;

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

		if(isset($_POST)){
			// Save the POST in the session and redirect back to the same URL (this time w/o POST data)
			session::set('POST', $_POST, TRUE);
			session::reflash(formBuilder::SESSION_SAVED_FORMS_KEY);
			http::redirect($_SERVER['REQUEST_URI'], NULL, TRUE);
		}

		// If we're here, then we need to restore POST
		if(!session::has('POST')){
			errorHandle::newError(__METHOD__."() Cannot find saved POST data!", errorHandle::DEBUG);
			return self::ERR_SYSTEM;
		}

		/*
		 * Extract the RAW data from _POST and pass it to process() for processing
		 *
		 * We use RAW here to avoid double-escaping when we process the data in process()
		 * This may happen because the developer will use process() to handle his own raw data
		 * Since the database module uses prepared statements, manually escaping the POST data is not necessary
		 */
		$post = session::get('POST');
		$this->process($post['RAW']);
	}

	public function process($data){
		// Save the data to our processData var
		$this->processData = $data;

		// Process field validation rules
		if(!$this->__processValidation()) return self::ERR_VALIDATION;

		switch($this->processorType){
			case self::TYPE_INSERT:
				return $this->__processInsert();
			case self::TYPE_UPDATE:
				return $this->__processUpdate();
			case self::TYPE_EDIT:
				return $this->__processEdit();
			default:
				errorHandle::newError(__METHOD__."() Invalid formType! (engineAPI bug)", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
		}

	}

	private function __processValidation(){
		// Validation
		$isValid   = TRUE;
		$validator = validate::getInstance();
		foreach($this->processData as $fieldName => $fieldData){
			// Get the fieldBuilder object
			$field = $this->getField($fieldName);

			// If no field definition, ignore the field all together (throw it away)
			if(!$field){
				errorHandle::newError(__METHOD__."() No field definition found for field '$fieldName'! (ignoring)", errorHandle::DEBUG);
				unset($this->processData[$fieldName]);
				continue;
			}

			// If no validation set, continue
			if(!$field->validate) continue;

			// Try and validate the data
			$result = method_exists($validator, $field->validate)
				? call_user_func(array($validator, $field->validate), $fieldData)
				: $validator->regexp($field->validate, $fieldData);

			// Did an error occur? (like a bad regex pattern)
			if($result === NULL) errorHandle::newError(__METHOD__."() Error occurred during validation for field '$fieldName'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);

			// Did validation fail?
			if(!$result){
				$isValid = FALSE;
				errorHandle::errorMsg($validator->getErrorMessage($field->validate, $fieldData));
			}
		}
		return $isValid;
	}

	private function __processInsert(){
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
		print $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES(%s)',
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

	private function __processUpdate(){
		$sqlFields = array();
		foreach($this->fields as $field){
			// Skip disabled fields
			if($field->disabled) continue;
			// Revert read-only fields to their original state
			if($field->readonly) $this->processData[ $field->name ] = $field->renderedValue;
			// Save the field for insertion
			$sqlFields[ $field->name ] = $this->processData[ $field->name ];
		}

		$where = '';


		$sql = sprintf('UPDATE `%s` SET `%s`=? WHERE %s LIMIT 1',
			$this->dbTable,
			implode('`=?,`', array_keys($sqlValues)),
			$where
		);

		$stmt = $this->db->query($sql, $sqlValues);
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! ({$stmt->errorCode()}:{$stmt->errorMsg()})", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}

		return self::ERR_OK;
	}

	private function __processEdit(){
		return self::ERR_OK;
	}
}