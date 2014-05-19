<?php

class formProcessor{
	const ERR_OK              = 0;
	const ERR_NO_POST         = 1;
	const ERR_NO_ID           = 2;
	const ERR_INVALID_ID      = 3;
	const ERR_VALIDATION      = 4;
	const ERR_SYSTEM          = 5;
	const ERR_TYPE            = 6;
	const ERR_INCOMPLETE_DATA = 7;

	/**
	 * @var array Human-readable error messages for our ERR types
	 */
	public static $errorMessages = array(
		self::ERR_OK              => 'Success',
		self::ERR_NO_POST         => 'No data received',
		self::ERR_NO_ID           => 'No formID received',
		self::ERR_INVALID_ID      => 'Invalid ID received',
		self::ERR_VALIDATION      => 'Validation error',
		self::ERR_SYSTEM          => 'Internal system error',
		self::ERR_TYPE            => 'Invalid formType',
		self::ERR_INCOMPLETE_DATA => 'Incomplete Data',
	);

	/**
	 * @var null|formBuilder A reference back to the formBuilder that created this formProcessor (May be NULL if created manual)
	 */
	public $formBuilder;

	/**
	 * @var int The internal type of form to be processed
	 */
	private $processorType;

	/**
	 * @var array List of callbacks
	 */
	private $callbacks = array(
		'beforeInsert' => NULL,
		'doInsert'     => NULL,
		'afterInsert'  => NULL,
		'beforeUpdate' => NULL,
		'doUpdate'     => NULL,
		'afterUpdate'  => NULL,
		'beforeEdit'   => NULL,
		'doEdit'       => NULL,
		'afterEdit'    => NULL,
		'beforeDelete' => NULL,
		'doDelete'     => NULL,
		'afterDelete'  => NULL,
		'onSuccess'    => NULL,
		'onFailure'    => NULL,
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
	 * @var string The insertID from the last insert() operation
	 */
	public $insertID;

	/**
	 * @var array Array of primary key values (used in self::__processEdit())
	 */
	public $primaryFieldsValues;

	/**
	 * @var formFields
	 */
	public $fields;

	public function __construct($dbTableName, $dbConnection=NULL){
		$this->db = ($dbConnection instanceof dbDriver)
			? $dbConnection
			: db::get('appDB');
		$this->dbTable = $this->db->escape($dbTableName);
		$this->fields = new formFields();
	}

	/**
	 * Add a field to the processor
	 *
	 * @param array|fieldBuilder $field
	 * @return bool
	 */
	public function addField($field){
		// Add the field
		$result = $this->fields->addField($field);
		if (!$result) {
			errorHandle::newError(__METHOD__."() Failed to add field!", errorHandle::DEBUG);
			return $result;
		}

		// Get the field we just added
		if(is_array($field)) $field = $this->fields->getField($field['name']);

		// If we added it successfully, handle any special cases
		if ($field->type == 'file') $this->formEncoding = 'multipart/form-data';

		return $result;
	}

	private function getFormScope(){
		return ($this->formBuilder instanceof formBuilder)
			? $this->formBuilder->formName.'_'.$this->processorType
			: $this->processorType;
	}

	/**
	 * Record a new form error
	 * @param string $msg
	 * @param string $type
	 */
	public function formError($msg, $type){
		switch($type){
			case errorHandle::ERROR:
				errorHandle::errorMsg($msg);
				break;
			case errorHandle::SUCCESS:
				errorHandle::successMsg($msg);
				break;
			case errorHandle::WARNING:
				errorHandle::warningMsg($msg);
				break;
		}

		// Add error to formErrors (if we can)
		if($this->formBuilder instanceof formBuilder){
			$this->formBuilder->formError($msg, $type, $this->getFormScope());
		}

		// TODO: Add onError callback logic
	}

	/**
	 * Sets the internal formType (insert, update, edit) which controls how the data is processed
	 * @param $input
	 */
	public function setProcessorType($input){
		$this->processorType = formBuilder::getFormType($input);
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
	 * @param array  $data
	 * @param string $defaultMethod
	 * @return mixed
	 */
	private function triggerCallback($trigger, $data=array(), $defaultMethod=NULL){
		if(!$this->triggerPresent($trigger) && isnull($defaultMethod)) return NULL;
		$fn = $this->callbacks[$trigger]
			? $this->callbacks[$trigger]
			: array($this, $defaultMethod);

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
			// Save the data for easy access
			$fieldData = $data[ $field->name ];

			// If this field is required, make sure it's present
			if (!isset($fieldData) || is_empty($fieldData)) {
				if ($field->required) {
					$isValid = FALSE;
					$this->formError("Field '$field->label' required!", errorHandle::ERROR);
				}
				continue;
			}

			// dupe checking
			if(!$field->duplicates && !is_empty($fieldData)){
				$sql = sprintf('SELECT COUNT(*) AS i FROM `%s` WHERE `%s`=?',
					$this->db->escape($this->dbTable),
					$this->db->escape($field->name));
				$stmt = $this->db->query($sql, array($fieldData));
				if($stmt->fetchField()){
					$isValid = FALSE;
					$this->formError("Duplicate value '".htmlSanitize($fieldData)."' found for field '{$field->label}'!", errorHandle::ERROR);
				}
			}

			// If no validation set, skip
			if(isnull($field->validate)) continue;

			// Try and validate the data
			$result = method_exists($validator, $field->validate)
				? call_user_func(array($validator, $field->validate), $fieldData)
				: $validator->regexp($field->validate, $fieldData);

			if($result === NULL){
				errorHandle::newError(__METHOD__."() Error occurred during validation for field '{$field->name}'! (possible regex error: ".preg_last_error().")", errorHandle::DEBUG);
			}elseif($result === FALSE){
				$isValid = FALSE;
				$this->formError($validator->getErrorMessage($field->validate, $fieldData), errorHandle::ERROR);
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

		// Start database transaction
		$this->db->beginTransaction();

		try{
			$fields = array();
			$deferredLinkedToFields = array();
			foreach($data as $field => $value){
				$field = $this->fields->getField($field);
				if(!($field instanceof fieldBuilder)) continue;

				if($field->usesLinkTable()){
					// Process the link table, no local field to process
					$deferredLinkedToFields[] = $field;
				}else{
					// Don't add this field if it's NULL
					if (isnull($data[ $field->name ])) continue;

					// Field doesn't use a link field, normalize arrays for single query
					$fields[ $field->name ] = $field->formatValue($data[ $field->name ]);
				}
			}

			if(sizeof($fields)){
				$sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
					$this->dbTable,
					implode('`,`',array_keys($fields)),
					implode(',',array_fill(0,sizeof($fields),'?')));
				$stmt = $this->db->query($sql, $fields);
				if($stmt->errorCode()){
					errorHandle::newError(__METHOD__."() SQL Error! {$stmt->errorCode()}:{$stmt->errorMsg()} ($sql)", errorHandle::HIGH);
					throw new Exception("Internal database error!", self::ERR_SYSTEM);
				}

				// Save the insertID for later usage
				$this->insertID = $stmt->insertId();

				// If there's any deferred linkedTo fields, process them
				if(sizeof($deferredLinkedToFields)){
					$primaryField = array_shift( $this->fields->listPrimaryFields()); // Shift 1st item off the array, ensures we get the 1st defined primary field
					$data[ $primaryField ] = $this->insertID;

					foreach($deferredLinkedToFields as $deferredLinkedToField){
						$this->processLinkedField($deferredLinkedToField, $data);
					}
				}
			}

			// Commit the transaction
			$this->db->commit();

		}catch(Exception $e){
			// Record the error
			$this->formError($e->getMessage(), errorHandle::ERROR);

			// Rollback the transaction
			$this->db->rollback();

			// Return the error code
			return $e->getCode();
		}

		// If we're here then all went well!
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
		if(! $this->validate($data)) return self::ERR_VALIDATION;

		// Get the list of primary fields
		$primaryFields =  $this->fields->listPrimaryFields();

		// Make sure we have all primary fields accounted for in $data
		$missingPrimaryKeys = array_diff($primaryFields, array_keys($data));
		if(sizeof($missingPrimaryKeys)){
			errorHandle::newError(__METHOD__."() Cannot update record! (missing primary keys ".implode(',',$missingPrimaryKeys)." in data)", errorHandle::DEBUG);
			return self::ERR_INCOMPLETE_DATA;
		}

		// Start database transaction
		$this->db->beginTransaction();

		try{
			$updateFields = array();
			$whereFields  = array();
			foreach($data as $field => $value){
				$field = $this->fields->getField($field);
				if(!($field instanceof fieldBuilder)) continue;

				if($field->usesLinkTable()){
					// Process the link table, no local field to process
					$this->processLinkedField($field, $data);
				}else{
					// Field doesn't use a link field, normalize arrays for single query

					// Format the value accorting to the field
					$value = $field->formatValue($value);

					// Put this field in the WHERE clause or in with the fields?
					if( $this->fields->isPrimaryField($field->name)){
						if(is_empty($value)){
							errorHandle::newError(__METHOD__."() Cannot update record! (primary field '{$field->name}' is empty)", errorHandle::DEBUG);
							return self::ERR_INCOMPLETE_DATA;
						}
						$whereFields[ $field->toSqlSnippet() ] = $value;
					}else{
						$updateFields[ $field->toSqlSnippet() ] = $value;
					}
				}
			}

			// Are there local fields to update?
			if(sizeof($updateFields) && sizeof($whereFields)){
				$sql = sprintf('UPDATE `%s` SET %s WHERE %s LIMIT 1',
					$this->dbTable,
					implode(',', array_keys($updateFields)),
					implode(' AND ', array_keys($whereFields)));
				$stmt = $this->db->query($sql, array_merge(array_values($updateFields), array_values($whereFields)));
				if($stmt->errorCode()){
					errorHandle::newError(__METHOD__."() SQL Error! {$stmt->errorCode()}:{$stmt->errorMsg()} ($sql)", errorHandle::HIGH);
					throw new Exception("Internal database error!", self::ERR_SYSTEM);
				}
			}

			// Commit the transaction
			$this->db->commit();

		}catch(Exception $e){
			// Record the error
			$this->formError($e->getMessage(), errorHandle::ERROR);

			// Rollback the transaction
			$this->db->rollback();

			// Return the error code
			return $e->getCode();
		}

		// If we're here then all went well!
		return self::ERR_OK;
	}

	/**
	 * @param fieldBuilder $field
	 * @param              $formData
	 * @return int
	 * @throws Exception
	 */
	private function processLinkedField(fieldBuilder $field, $formData){
		// Make sure there is only 1 primary field (TODO: Add multi-key support)
		if(sizeof( $this->fields->listPrimaryFields()) > 1){
			errorHandle::newError(__METHOD__."() Cannot process linked field! (multiple primary fields not yet supported)", errorHandle::HIGH);
			throw new Exception('Internal configuration error!', self::ERR_SYSTEM);
		}

		// Get the field's linkedTo metadata and it's data
		$linkedTo         = $field->linkedTo;
		$db               = isset($linkedTo['dbConnection'])     ? db::get($linkedTo['dbConnection']) : $this->db;
		$linkTable        = isset($linkedTo['linkTable'])        ? $linkedTo['linkTable']             : '';
		$linkLocalField   = isset($linkedTo['linkLocalField'])   ? $linkedTo['linkLocalField']        : '';
		$linkForeignField = isset($linkedTo['linkForeignField']) ? $linkedTo['linkForeignField']      : '';

		// Make sure we have the linkTable metadata
		if(isnull($linkTable) || isnull($linkLocalField) || isnull($linkForeignField)){
			errorHandle::newError(__METHOD__."() Missing required many-to-many link metadata! (Required: linkTable, linkForeignField, linkLocalField)", errorHandle::HIGH);
			throw new Exception('Internal configuration error!', self::ERR_SYSTEM);
		}

		// Get this field's data and the form's primary key
		$fieldData    = (array)$formData[ $field->name ];
		$primaryField = array_shift( $this->fields->listPrimaryFields()); // Shift 1st item off the array, ensures we get the 1st defined primary field
		$primaryValue = $formData[$primaryField];

		// Clean linkedTo's table name (we'll be using it a lot)
		$linkTable = $db->escape($linkTable);

		// Get all current assignments
		$sql = sprintf('SELECT `%s` FROM `%s` WHERE `%s`=?',
			$db->escape($linkForeignField),
			$db->escape($linkTable),
			$db->escape($linkLocalField));
		$selectStmt = $db->query($sql, array($primaryValue));
		if($selectStmt->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error: {$selectStmt->errorCode()}:{$selectStmt->errorMsg()}! (SQL: $sql)", errorHandle::DEBUG);
			throw new Exception('Internal database error!', self::ERR_SYSTEM);
		}
		$currentValues = $selectStmt->fetchFieldAll();

		// Figure out what's been added and add them
		$added = array_diff($fieldData, $currentValues);
		$insertSQL = sprintf('INSERT INTO `%s` (%s,%s) VALUES(?,?)',
			$db->escape($linkTable),
			$db->escape($linkLocalField),
			$db->escape($linkForeignField));
		foreach($added as $addedValue){
			$addedStmt = $db->query($insertSQL, array($primaryValue, $addedValue));
			if($addedStmt->errorCode()){
				errorHandle::newError(__METHOD__."() SQL Error: {$addedStmt->errorCode()}:{$addedStmt->errorMsg()}! (SQL: $insertSQL)", errorHandle::DEBUG);
				throw new Exception('Internal database error!', self::ERR_SYSTEM);
			}
		}

		// Figure out what's been removed and remove them
		$removed = array_diff($currentValues, $fieldData);
		$deleteSQL = sprintf('DELETE FROM `%s` WHERE `%s`=? AND `%s`=? LIMIT 1',
			$db->escape($linkTable),
			$db->escape($linkLocalField),
			$db->escape($linkForeignField));
		foreach($removed as $removedValue){
			$removedStmt = $db->query($deleteSQL, array($primaryValue, $removedValue));
			if($removedStmt->errorCode()){
				errorHandle::newError(__METHOD__."() SQL Error: {$removedStmt->errorCode()}:{$removedStmt->errorMsg()}! (SQL: $deleteSQL)", errorHandle::DEBUG);
				throw new Exception('Internal database error!', self::ERR_SYSTEM);
			}
		}

		// If we get here, then all is well
		return self::ERR_OK;

		/*
		 * TODO: This is a start to supporting multi-keys. Hopefully we'll revisit this someday
		 * -------------------------------------------------------------------------------------
		 *
		// Build up the local field mappings
		$localFieldMapping = array();
		$localValueMapping = array();
		if(is_string($linkedTo['linkLocalField'])){
			$linkLocalField = $this->db->escape($linkedTo['linkForeignField']);
			$primaryFields =  $this->fields->getPrimaryFields();
			if(sizeof($primaryFields) > 1){
				errorHandle::newError(__METHOD__."() More than 1 primary field defined in form definition", errorHandle::DEBUG);
				throw new Exception('Internal configuration error!', self::ERR_SYSTEM);
			}else{
				$fieldName = $primaryFields[0]->name;
				$localFieldMapping[$linkLocalField] = "$linkLocalField = :$fieldName";
				$localValueMapping[":$fieldName"] = $formData[$fieldName];
			}
		}else{
			foreach($linkedTo['linkLocalField'] as $fieldName => $linkLocalField){
				if( $this->fields->isPrimaryField($fieldName)){
					errorHandle::newError(__METHOD__."() '$fieldName' is not a primary field!", errorHandle::DEBUG);
					throw new Exception('Internal configuration error!', self::ERR_SYSTEM);
				}
				$localFieldMapping[$linkLocalField] = "$linkLocalField = :$fieldName";
				$localValueMapping[":$fieldName"] = $formData[$fieldName];
			}
		}

		// Build up the foreign field mappings
		$foreignFieldMapping = array();
		$foreignValueMapping = array();
		if(is_string($linkedTo['linkForeignField'])){
			$linkForeignField = $this->db->escape($linkedTo['linkForeignField']);
			$foreignFieldMapping[$linkForeignField] = "$linkForeignField = :$linkForeignField";
			foreach($fieldData as $fieldDataValue){
				$foreignValueMapping[][":$linkForeignField"] = $fieldDataValue;
			}
		}else{
		}

		// Wipe all links
		$sql = sprintf("DELETE FROM `%s` WHERE %s",
			$linkTable,
			implode(' AND ', array_values($localFieldMapping)));
		$deleteAllStmt = $pdo->prepare($sql);
		if(!$deleteAllStmt->execute($localValueMapping)){
			$errorInfo = $deleteAllStmt->errorInfo();
			errorHandle::newError(__METHOD__."() SQL Error! {$errorInfo[1]}:{$errorInfo[2]} ($sql)", errorHandle::HIGH);
			throw new Exception('Internal database error!', self::ERR_SYSTEM);
		}


		// Loop through submission and re-add links
		$addLinkStmt = $pdo->prepare(sprintf('INSERT INTO `%s` (%s) VALUE(%s)',
			$linkTable,
			array_merge(array_keys($localFieldMapping), array_keys($foreignFieldMapping)),
			array_merge(array_keys($localValueMapping), array_keys($foreignValueMapping))));
		foreach($foreignValueMapping as $foreignValueMappingRow){
			if(!$addLinkStmt->execute($foreignValueMappingRow)){
				$errorInfo = $addLinkStmt->errorInfo();
				errorHandle::newError(__METHOD__."() SQL Error! {$errorInfo[1]}:{$errorInfo[2]} ($sql)", errorHandle::HIGH);
				throw new Exception('Internal database error!', self::ERR_SYSTEM);
			}
		}
		*/
	}

	/**
	 * Process POST data
	 * @return int
	 */
	public function processPost(){
		if (!$this->processorType) return self::ERR_TYPE;

		if(!session::has('POST')){
			if(sizeof($_POST)){
				// Save the uploaded files in the session
				if (sizeof($_FILES)) {
					foreach ($_FILES as $name => $file) {
						if (isset($file['tmp_name']) && !is_empty($file['tmp_name'])) {
							$_FILES[$name]['data'] = file_get_contents($file['tmp_name']);
						}
					}
					session::set('FILES', $_FILES, array('location' => 'flash'));
				}

				// Save the POST in the session and redirect back to the same URL (this time w/o POST data)
				session::set('POST', $_POST, array('location' => 'flash'));
				http::redirect($_SERVER['REQUEST_URI'], 303, TRUE);
			}else{
				errorHandle::newError(__METHOD__."() Cannot find saved POST data!", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
			}
		}

		// Restore $_FILES array
		$_FILES = session::get('FILES');
		session::destroy('FILES');
		if (sizeof($_FILES)) {
			foreach ($_FILES as $name => $file) {
				if (isset($file['data']) && !is_empty($file['data'])) {
					file_put_contents($file['tmp_name'], $file['data']);
				}
			}
		}

		// Restore $_POST array
		$_POST = session::get('POST');
		session::destroy('POST');

		/*
		 * Extract the RAW data from _POST and pass it to process() for processing
		 *
		 * We use RAW here to avoid double-escaping when we process the data in process()
		 * This may happen because the developer will use process() to handle his own raw data
		 * Since the database module uses prepared statements, manually escaping the POST data is not necessary
		 */
		$result = $this->process($_POST['RAW']);
		session::destroy(formBuilder::SESSION_SAVED_FORMS_KEY);
		return $result;
	}

	/**
	 * Process raw data
	 * @param array $data
	 * @return int
	 */
	public function process($data){
		switch($this->processorType){
			case formBuilder::TYPE_INSERT:
				$result = $this->__processInsert($data);
				break;

			case formBuilder::TYPE_UPDATE:
				$result = $this->__processUpdate($data);
				break;

			case formBuilder::TYPE_EDIT:
				$result = $this->__processEdit($data);
				break;

			default:
				errorHandle::newError(__METHOD__."() Invalid formType! (engineAPI bug)", errorHandle::DEBUG);
				return self::ERR_SYSTEM;
		}
		if($result === self::ERR_OK) $this->formError('Form submission successful!', errorHandle::SUCCESS);
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

		// Trigger beforeInsert event
		if($this->triggerPresent('beforeInsert')) $insertData = $this->triggerCallback('beforeInsert', array($insertData));

		// Trigger doInsert event
		$doInsert = $this->triggerCallback('doInsert', array($insertData), '__insertRow');
		if($doInsert !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doInsert, $insertData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doInsert;
			}
		}

		// Trigger afterInsert event
		$this->triggerCallback('afterInsert', array($this->insertID, $insertData));

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
			if( $this->fields->isPrimaryField($field)){
				$updateData[ $field->name ] = $field->value;
				continue;
			}

			// Skip disabled fields
			if($field->disabled) continue;

			// Skip fields not set
			if(!isset($data[ $field->name ])){
				if($field->usesLinkTable()) $updateData[ $field->name ] = array();
				continue;
			}

			// Revert read-only fields to their original state
			if($field->readonly) $data[ $field->name ] = $field->renderedValue;

			// Save the field for insertion
			$updateData[ $field->name ] = $data[ $field->name ];
		}

		// Trigger beforeUpdate event
		if($this->triggerPresent('beforeUpdate')) $updateData = $this->triggerCallback('beforeUpdate', array($updateData));

		// Trigger doUpdate event
		$doUpdate = $this->triggerCallback('doUpdate', array($updateData), '__updateRow');
		if($doUpdate !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doUpdate, $updateData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doUpdate;
			}
		}

		// Trigger afterUpdate event
		$this->triggerCallback('afterUpdate', array($updateData));

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
			$field =  $this->fields->getField($fieldName);

			// Skip undefined fields, or system/special fields
			if($field === NULL || $field->isSystem() || $field->isSpecial()) continue;

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
		$deletedIDs = isset($data['__deleted']) ? $data['__deleted'] : array();
		if(sizeof($deletedIDs)){
			foreach($deletedIDs as $deletedRowID){
				// Save the row's data and then delete it from the array
				$deletedRowData = $updateRowData[$deletedRowID];
				unset($updateRowData[$deletedRowID]);

				// Merge in the row's primary fields
				$deletedRowData = array_merge($deletedRowData, $this->primaryFieldsValues[$deletedRowID]);

				// Save the row to $deletedRows
				$deletedRows[] = $deletedRowData;
			}
		}

		// Strip rowID off updatedRows now that deletedRows is build (as it's no longer needed)
		$updateRowData = array_values($updateRowData);

		// Trigger beforeDelete event
		if($this->triggerPresent('beforeDelete')) $deletedRows = $this->triggerCallback('beforeDelete', array($deletedRows));

		// Trigger doDelete event
		$doDelete = $this->triggerCallback('doDelete', array($deletedRows), '__deleteRows');
		if($doDelete !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doDelete, $deletedRows));
				if(!$onFailure) return $onFailure;
			}else {
				return $doDelete;
			}
		}

		// Trigger afterDelete event
		$this->triggerCallback('afterDelete', array($deletedRows));

		// Trigger beforeEdit event
		if($this->triggerPresent('beforeEdit')) $updateRowData = $this->triggerCallback('beforeEdit', array($updateRowData));

		// Trigger doEdit event
		$doEdit = $this->triggerCallback('doEdit', array($updateRowData), '__updateRows');
		if($doEdit !== self::ERR_OK) {
			if ($this->triggerPresent('onFailure')) {
				$onFailure = $this->triggerCallback('onFailure', array($doEdit, $updateRowData));
				if(!$onFailure) return $onFailure;
			}else {
				return $doEdit;
			}
		}

		// Trigger afterEdit event
		$this->triggerCallback('afterEdit', array($updateRowData));

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
					// If this field uses linkTable, DON'T ALTER ITS VALUE!
					if($field->usesLinkTable()) break;
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

		try{
			$this->db->beginTransaction();

			$linkPrimaryValue = NULL;
			$primaryFieldsSQL = array();
			foreach($this->fields as $field){
				// If this is a primary field
				if($field->isPrimary()){
					// If this is the 1st primary value, save it for the linkTable stuff below
					if(sizeof($primaryFieldsSQL) == 0) $linkPrimaryValue = $data[ $field->name ];
					// Add this field to the list for the final SQL
					$primaryFieldsSQL[ $field->toSqlSnippet() ] = $data[ $field->name ];
				}

				// If this field uses a linkTable (many-to-many) then we need to delete the links as well
				if($field->usesLinkTable()){
					$linkedTo       = $field->linkedTo;
					$db             = isset($linkedTo['dbConnection']) ? db::get($linkedTo['dbConnection']) : $this->db;
					$linkTable      = isset($linkedTo['linkTable']) ? $linkedTo['linkTable'] : '';
					$linkLocalField = isset($linkedTo['linkLocalField']) ? $linkedTo['linkLocalField'] : '';
					$deleteLinkSQL  = sprintf('DELETE FROM `%s` WHERE `%s`=?',
						$db->escape($linkTable),
						$db->escape($linkLocalField));
					$deleteLinkSTMT = $db->query($deleteLinkSQL, array($linkPrimaryValue));
					if($deleteLinkSTMT->errorCode()){
						errorHandle::newError(__METHOD__."() SQL Error! ({$deleteLinkSTMT->errorCode()}:{$deleteLinkSTMT->errorMsg()})", errorHandle::HIGH);
						throw new Exception('Internal database error!', self::ERR_SYSTEM);
					}
				}
			}

			// Now delete the record itself
			$sql = sprintf('DELETE FROM `%s` WHERE %s LIMIT 1',
				$this->dbTable,
				implode(' AND ', array_keys($primaryFieldsSQL)));
			$stmt = $this->db->query($sql, array_values($primaryFieldsSQL));
			if($stmt->errorCode()){
				errorHandle::newError(__METHOD__."() SQL Error! ({$stmt->errorCode()}:{$stmt->errorMsg()})", errorHandle::HIGH);
				throw new Exception('Internal database error!', self::ERR_SYSTEM);
			}

			$this->db->commit();
		}catch(Exception $e){
			$this->db->rollback();
			$this->formError($e->getMessage(), errorHandle::ERROR);
			return $e->getCode();
		}

		return self::ERR_OK;
	}
}
