<?php

class formProcessor extends formFields{
	const ERR_OK              = 0;
	const ERR_NO_POST         = 1;
	const ERR_NO_ID           = 2;
	const ERR_INVALID_ID      = 3;
	const ERR_VALIDATION      = 4;
	const ERR_SYSTEM          = 5;
	const ERR_TYPE            = 6;
	const ERR_INCOMPLETE_DATA = 7;

	const TYPE_INSERT = 1;
	const TYPE_UPDATE = 2;
	const TYPE_EDIT   = 3;

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
	 * @var int The internal type of form to be processed
	 */
	private $processorType;

	/**
	 * @var array List of callbacks
	 */
	private $callbacks = array(
		'beforeInsert'    => NULL,
		'doInsert'        => NULL,
		'beforeUpdate'    => NULL,
		'doUpdate'        => NULL,
		'beforeEdit'      => NULL,
		'doEdit'          => NULL,
		'beforeDelete'    => NULL,
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
	private function triggerCallback($trigger, $data=array(), $default=NULL){
		if(!$this->callbacks[$trigger] && isnull($default)) return NULL;
		$fn = $this->callbacks[$trigger]
			? $this->callbacks[$trigger]
			: array($this, $default);

		// Make sure this object gets added as the 1st param of the callback
		array_unshift($data, $this);

		return call_user_func_array($fn, $data);
	}

	/**
	 * Perform validation rules against $data
	 * @param array $data
	 * @return bool
	 */
	public function validate($data){
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
				// TODO: Trigger onValidateError event
				errorHandle::errorMsg($validator->getErrorMessage($field->validate, $fieldData));
				continue;
			}

			// Did an error occur? (like a bad regex pattern)
			if($result === NULL) errorHandle::newError(__METHOD__."() Error occurred during validation for field '{$field->name}'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);

		}
		return $isValid;
	}

	/**
	 * Perform INSERT operation based on the given data
	 *
	 * This method expects $data to be a key=>value array of all fields
	 * ready to go with no additional processing needed.
	 *
	 * @param array $data
	 * @return int
	 */
	public function insert($data){
		// Process field validation rules
		if(!$this->validate($data)) return self::ERR_VALIDATION;

		$fields = array();
		foreach($data as $field => $value){
			$field = $this->getField($field);
			$fields[ $field->name ] = $data[ $field->name ];
		}

		$sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
			$this->dbTable,
			implode('`,`',array_keys($fields)),
			implode(',',array_fill(0,sizeof($fields),'?')));
		$stmt = $this->db->query($sql, $fields);
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! {$stmt->errorCode()}:{$stmt->errorMsg()} ($sql)", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}

		return self::ERR_OK;
	}

	/**
	 * Perform UPDATE operation based on the given data
	 *
	 * This method expects $data to be a key=>value array of all fields (primary and not)
	 * ready to go with no additional processing needed.
	 *
	 * @param array $data
	 * @return int
	 */
	public function update($data){
		// Process field validation rules
		if(!$this->validate($data)) return self::ERR_VALIDATION;

		// Get the list of primary fields
		$primaryFields = $this->listPrimaryFields();

		// Make sure we have all primary fields accounted for in $data
		$missingPrimaryKeys = array_diff($primaryFields, array_keys($data));
		if(sizeof($missingPrimaryKeys)){
			errorHandle::newError(__METHOD__."() Cannot update record! (missing primary keys ".implode(',',$missingPrimaryKeys)." in data)", errorHandle::DEBUG);
			return self::ERR_INCOMPLETE_DATA;
		}

		$updateFields = array();
		$whereFields  = array();
		foreach($data as $field => $value){
			$field = $this->getField($field);
			if($this->isPrimaryField($field->name)){
				if(is_empty($value)){
					errorHandle::newError(__METHOD__."() Cannot update record! (primary field '{$field->name}' is empty)", errorHandle::DEBUG);
					return self::ERR_INCOMPLETE_DATA;
				}
				$whereFields[ $field->toSqlSnippet() ] = $value;
			}else{
				$updateFields[ $field->toSqlSnippet() ] = $value;
			}
		}

		$sql = sprintf('UPDATE `%s` SET %s WHERE %s LIMIT 1',
			$this->dbTable,
			implode(',', array_keys($updateFields)),
			implode(' AND ', array_keys($whereFields)));
		$stmt = $this->db->query($sql, array_merge(array_values($updateFields), array_values($whereFields)));
		if($stmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error! {$stmt->errorCode()}:{$stmt->errorMsg()} ($sql)", errorHandle::HIGH);
			return self::ERR_SYSTEM;
		}

		return self::ERR_OK;
	}

	/**
	 * Process POST data
	 * @return int
	 */
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

	/**
	 * Process raw data
	 * @param array $data
	 * @return int
	 */
	public function process($data){
		switch($this->processorType){
			case self::TYPE_INSERT:
				$result = $this->__processInsert($data);
				break;

			case self::TYPE_UPDATE:
				$result = $this->__processUpdate($data);
				break;

			case self::TYPE_EDIT:
				$result = $this->__processEdit($data);
				break;

			default:
				errorHandle::newError(__METHOD__."() Invalid formType! (engineAPI bug)", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
		}
		if($result === self::ERR_OK) errorHandle::successMsg('Form submission successful!');
		return $result;
	}

	/**
	 * [Internal helper] Process data from an insertForm
	 * @param array $data
	 * @return int
	 */
	private function __processInsert($data){
		$insertData = array();
		foreach($this->fields as $field){
			// Skip system or special fields
			if($field->isSystem() || $field->isSpecial()) continue;

			// Skip disabled fields
			if($field->disabled) continue;

			// Revert read-only fields to their original state
			if($field->readonly) $data[ $field->name ] = $field->renderedValue;

			// Save the field for insertion
			if(isset($data[ $field->name ])) $insertData[ $field->name ] = $data[ $field->name ];
		}

		// Trigger beforeInsert and doInsert events
		if($this->triggerPresent('beforeInsert')) $insertData = $this->triggerCallback('beforeInsert', array($insertData));
		$doInsert = $this->triggerCallback('doInsert', array($insertData), '__insertRow');
		if($doInsert !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doInsert, $insertData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doInsert;
			}
		}

		// Trigger onSuccess event
		return $this->triggerPresent('onSuccess')
			? $this->triggerCallback('onSuccess')
			: self::ERR_OK;
	}

	/**
	 * [Internal helper] Process data from an updateForm
	 * @param array $data
	 * @return int
	 */
	private function __processUpdate($data){
		$updateData = array();

		foreach($this->fields as $field){
			// Skip system or special fields
			if($field->isSystem() || $field->isSpecial()) continue;

			// If this is a primary field, reset its value back to the saved one (dropping any user munging)
			if($this->isPrimaryField($field)){
				$updateData[ $field->name ] = $field->value;
				continue;
			}

			// Skip disabled fields
			if($field->disabled) continue;

			// Skip fields not set
			if(!isset($data[ $field->name ])) continue;

			// Revert read-only fields to their original state
			if($field->readonly) $data[ $field->name ] = $field->renderedValue;

			// Save the field for insertion
			$updateData[ $field->name ] = $data[ $field->name ];
		}

		if($this->triggerPresent('beforeUpdate')) $updateData = $this->triggerCallback('beforeUpdate', array($updateData));
		$doUpdate = $this->triggerCallback('doUpdate', array($updateData), '__updateRow');
		if($doUpdate !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doUpdate, $updateData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doUpdate;
			}
		}

		// Trigger onSuccess event
		return $this->triggerPresent('onSuccess')
			? $this->triggerCallback('onSuccess')
			: self::ERR_OK;
	}

	/**
	 * [Internal helper] Process data from an editTable
	 * @param array $data
	 * @return int
	 */
	private function __processEdit($data){
		// Normalize data
		$updateRowData = array();
		foreach($data as $fieldName => $fieldRows){
			$field = $this->getField($fieldName);

			// Skip system or special fields
			if($field->isSystem() || $field->isSpecial()) continue;

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
		$deletedRowIDs = array_filter(explode(',', $data['__deleted']));
		foreach($deletedRowIDs as $deletedRowID){
			// Save the row's data and then delete it from the array
			$deletedRowData = $updateRowData[$deletedRowID];
			unset($updateRowData[$deletedRowID]);

			// Merge in the row's primary fields
			$deletedRowData = array_merge($deletedRowData, $this->primaryFieldsValues[$deletedRowID]);

			// Save the row to $deletedRows
			$deletedRows[] = $deletedRowData;
		}

		// Strip rowID off updatedRows now that deletedRows is build (and it's no longer needed)
		$updateRowData = array_values($updateRowData);

		// Trigger beforeDelete and doDelete events
		if($this->triggerPresent('beforeDelete')) $deletedRows = $this->triggerCallback('beforeDelete', array($deletedRows));
		$doDelete = $this->triggerCallback('doDelete', array($deletedRows), '__deleteRows');
		if($doDelete !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doDelete, $deletedRows));
				if(!$onFailure) return $onFailure;
			}else {
				return $doDelete;
			}
		}

		// Trigger beforeEdit and doEdit events
		if($this->triggerPresent('beforeEdit')) $updateRowData = $this->triggerCallback('beforeEdit', array($updateRowData));
		$doEdit = $this->triggerCallback('doEdit', array($updateRowData), '__updateRows');
		if($doEdit !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doEdit, $updateRowData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doEdit;
			}
		}

		// Trigger onSuccess event
		return $this->triggerPresent('onSuccess')
			? $this->triggerCallback('onSuccess')
			: self::ERR_OK;
	}

	/**
	 * [Callback] Default callback for doDelete
	 * @param self $processor
	 * @param array $rows
	 * @return int
	 */
	private function __deleteRows($processor, $rows){
		foreach($rows as $row){
			if(self::ERR_OK != ($return = $processor->delete($row))) return $return;
		}
		return self::ERR_OK;
	}

	/**
	 * [Callback] Default callback for doInsert
	 * @param self $processor
	 * @param array $data
	 * @return int
	 */
	private function __insertRow($processor, $data){
		return $processor->insert($data);
	}

	/**
	 * [Callback] Default callback for doEdit
	 * @param self $processor
	 * @param array $data
	 * @return int
	 */
	private function __updateRows($processor, $data){
		$errorCode = self::ERR_OK;
		foreach($data as $row){
			// Update the row and handle the result
			$result = $processor->triggerCallback('doUpdate', array($row), '__updateRow');
			switch($result){
				// Continue if it fails validation
				case self::ERR_VALIDATION:
					$errorCode = self::ERR_VALIDATION;
					continue;
				// Continue if it fails due to incomplete data
				case self::ERR_INCOMPLETE_DATA:
					$errorCode = self::ERR_INCOMPLETE_DATA;
					continue;
				// Return if we have a system error
				case self::ERR_SYSTEM:
					return self::ERR_SYSTEM;
			}
		}
		return $errorCode;
	}

	/**
	 * [Callback] Default callback for doUpdate
	 * @param self $processor
	 * @param array $data
	 * @return int
	 */
	private function __updateRow($processor, $data) {
		foreach($processor->fields as $field){
			switch($field->type){
				case 'boolean':
				case 'checkbox':
					if(!isset($data[ $field->name ])) $data[ $field->name ] = '';
					break;
			}
		}

		return $processor->update($data);
	}

	/**
	 * Delete a given record from the database
	 *
	 * @TODO Discuss referential integrity checking
	 *
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