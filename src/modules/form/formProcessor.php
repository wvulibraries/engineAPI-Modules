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

	/**
	 * @var array Human-readable error messages for our ERR types
	 */
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
	 * @var array List of callbacks
	 */
	private $callbacks = array(
		'beforeInsert'    => NULL,
		'beforeUpdate'    => NULL,
		'beforeEdit'      => NULL,
		'doEdit'          => NULL,
		'doDelete'        => NULL,
		'onSuccess'       => NULL,
		'onFailure'       => NULL,
		'onValidateError' => NULL,
	);

	/**
	 * @var dbDriver The database connection
	 */
	private $db;

	/**
	 * @var string The database table
	 */
	private $dbTable;

	/**
	 * @var array Array of deleted rows (rows to be deleted)
	 */
	private $deletedRows;

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

	/**
	 * Sets a given callback to the given trigger
	 * @param string   $trigger
	 * @param callable $callback
	 * @return bool
	 */
	public function setCallback($trigger, $callback){
		if(!array_key_exists($trigger, $this->callbacks)){
			errorHandle::newError(__METHOD__."() Invalid callback trigger! (Valid triggers: ".implode(',', array_keys($this->callbacks)).")", errorHandle::DEBUG);
			return FALSE;
		}
		if(!is_callable($callback)){
			errorHandle::newError(__METHOD__."() Given callback is not callable!", errorHandle::DEBUG);
			return FALSE;
		}

		// If there's already a callback set, issue a notice
		if($this->callbacks[$trigger]) errorHandle::newError(__METHOD__."() Notice: Existing callback present for trigger ''. (Will override with new callback)", errorHandle::LOW);

		// Set the callback and return
		$this->callbacks[$trigger] = $callback;
		return TRUE;
	}

	/**
	 * Removes the current callback from the given trigger
	 * @param string $trigger
	 * @return bool
	 */
	public function clearCallback($trigger){
		if(!array_key_exists($trigger, $this->callbacks)){
			errorHandle::newError(__METHOD__."() Invalid callback trigger! (Valid triggers: ".implode(',', array_keys($this->callbacks)).")", errorHandle::DEBUG);
			return FALSE;
		}
		$this->callbacks[$trigger] = NULL;
		return TRUE;
	}

	/**
	 * Returns TRUE if the given trigger has a callback assigned to it
	 * @param $trigger
	 * @return bool
	 */
	public function triggerPresent($trigger){
		return $this->callbacks[$trigger] !== NULL;
	}

	/**
	 * [Internal helper] Trigger a callback
	 * @param string $trigger
	 * @param string $default
	 * @param array $data
	 * @return mixed
	 */
	private function triggerCallback($trigger, $data, $default=NULL){
		if(!$this->callbacks[$trigger] && isnull($default)) return NULL;
		$fn = $this->callbacks[$trigger]
			? $this->callbacks[$trigger]
			: array($this, $default);

		// Make sure this object gets added as the 1st param of the callback
		array_unshift($data, $this);

		return call_user_func_array($fn, $data);
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
			// If this field is required, make sure it's present
			if($field->required && !isset($data[ $field->name ]) && !$validator->present($data[ $field->name ])){
				$isValid = FALSE;
				errorHandle::errorMsg("Field '$field->label' required!");
				continue;
			}

			// If we're here, and it's not set then it is an optional field
			if(!isset($data[ $field->name ])) continue;

			// If no validation set, skip
			if(isnull($field->validate)) continue;

			// Save the data for easy access
			$fieldData = $data[ $field->name ];

			// Try and validate the data
			$result = method_exists($validator, $field->validate)
				? call_user_func(array($validator, $field->validate), $fieldData)
				: $validator->regexp($field->validate, $fieldData);

			if($result === NULL){
				errorHandle::newError(__METHOD__."() Error occurred during validation for field '{$field->name}'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);
			}elseif($result === FALSE){
				$isValid = FALSE;
				errorHandle::errorMsg($validator->getErrorMessage($field->validate, $fieldData));
				continue;
			}

			// Did an error occur? (like a bad regex pattern)
			if($result === NULL) errorHandle::newError(__METHOD__."() Error occurred during validation for field '{$field->name}'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);

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
			// Skip system fields
			if(0 === strpos($field->name, '__')) continue;

			// Skip special fields
			if(in_array($field->type, array('submit','reset','button'))) continue;

			// Skip disabled fields
			if($field->disabled) continue;

			// Revert read-only fields to their original state
			if($field->readonly) $data[ $field->name ] = $field->renderedValue;

			// Skip the field if there's no data provided (and catch edge-cases like checkbox)
			if(!isset($data[ $field->name ])){
				switch($field->type){
					case 'boolean':
					case 'checkbox':
						$data[ $field->name ] = 0;
						break;
					default:
						continue 2;
				}
			}

			// Save the field for insertion
			$sqlFields[ $field->toSqlSnippet() ] = isset($data[ $field->name ])
				? $data[ $field->name ]
				: $field->value;
		}

		$primaryFieldsSQL = array();
		foreach ($this->getPrimaryFields() as $primaryField) {
			$primaryFieldsSQL[$primaryField->toSqlSnippet()] = isset($primaryField->value) ? $primaryField->value : $data[ $primaryField->name ];
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
		// Normalize data
		$updateRowData = array();
		foreach($this->processData as $fieldName => $fieldRows){
			// Skip system fields
			if(0 === strpos($fieldName, '__')) continue;
			// Skip special fields
			if(in_array($fieldName, array('submit','reset','button'))) continue;
			// Foreach row, merge it's primary keys back in
			foreach((array)$fieldRows as $rowID => $rowData){
				$updateRowData[$rowID][$fieldName] = $rowData;
			}
		}
		foreach($this->primaryFieldsValues as $rowID => $rowData){
			foreach($rowData as $field => $value){
				$updateRowData[$rowID][$field] = $value;
			}
		}

		// Build list of deleted rows
		$deletedRows = array();
		$deletedRowIDs = array_filter(explode(',', $this->processData['__deleted']));
		foreach($deletedRowIDs as $deletedRowID){
			// Save the row's data and then delete it from the array
			$deletedRowData = $updateRowData[$deletedRowID];
			unset($updateRowData[$deletedRowID]);

			// Merge in the row's primary fields
			$deletedRowData = array_merge($deletedRowData, $this->primaryFieldsValues[$deletedRowID]);

			// Save the row to $deletedRows
			$deletedRows[] = $deletedRowData;
		}

		// Trigger callback for deleted rows
		$this->triggerCallback('doDelete', array($deletedRows), '__deleteRows');

		// Trigger callback for updated rows (after stripping off rowID)
		$updateRowData = array_values($updateRowData);
		if($this->triggerPresent('beforeEdit')){
			$updateRowData = $this->triggerCallback('beforeEdit', array($updateRowData));
		}

		// Trigger callback for updated rows (after stripping off rowID)
		return $this->triggerCallback('doEdit', array($updateRowData), '__processEditCallback');
	}

	private function __processEditCallback($processor, $data){
		$errorCode = self::ERR_OK;
		foreach($data as $row){
			// Update the row and handle the result
			switch($this->__processUpdate($row)){
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

	private function __deleteRows($processor, $rows){
		foreach($rows as $row){
			if(self::ERR_OK != ($return = $this->delete($row))) return $return;
		}
		return self::ERR_OK;
	}

	/**
	 * Delete a given record from the database
	 * @param array $data
	 * @return int
	 */
	public function delete($data){
		$primaryFieldsSQL = array();
		foreach ($this->listPrimaryFields() as $fieldName) {
			$fieldSQL = $this->getField($fieldName)->toSqlSnippet();
			$primaryFieldsSQL[$fieldSQL] = $data[$fieldName];
		}

		$sql = sprintf('DELETE FROM `%s` WHERE %s LIMIT 1',
			$this->dbTable,
			implode(' AND ', array_keys($primaryFieldsSQL)));
		$stmt = $this->db->query($sql, array_values($primaryFieldsSQL));
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! ({$stmt->errorCode()}:{$stmt->errorMsg()})", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}
		return self::ERR_OK;
	}
}