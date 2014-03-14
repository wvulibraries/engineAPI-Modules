<?php

abstract class formFields implements Countable{
	const DEFAULT_ORDER = '_z_';

	/**
	 * @var fieldBuilder[]
	 */
	protected $fields = array();

	/**
	 * @var array Index of field labels (to maintain uniqueness)
	 */
	protected $fieldLabels = array();

	/**
	 * @var array Index of all field IDs (to maintain uniqueness)
	 */
	protected $fieldIDs = array();

	/**
	 * @var array Store the ordering of the fields
	 */
	protected $fieldOrdering = array();

	/**
	 * @var array[] Array of field names in sorted order
	 */
	private $orderedFields = array();

	/**
	 * @var fieldBuilder[] Array of all primary fields
	 */
	protected $primaryFields = array();

	/**
	 * Returns the number of fields
	 *
	 * @return int
	 */
	function count(){
		return sizeof($this->fields);
	}

	/**
	 * Add a field
	 *
	 * @param array|fieldBuilder $field
	 * @return bool
	 */
	public function addField($field){
		// If we got an array or string, make it a fieldBuilder
		if (is_array($field) || is_string($field)) $field = fieldBuilder::createField($field);

		// Make sure we're working with a fieldBuilder
		if (!($field instanceof fieldBuilder)) return FALSE;

		// Make sure the field name is unique
		if (isset($this->fields[$field->name])) return FALSE;

		// If there's a label, make sure it's unique
		if (!is_empty($field->label) && in_array($field->label, $this->fieldLabels)) return FALSE;

		// If there's a field ID, make sure it's unique
		if (!is_empty($field->fieldID) && in_array($field->fieldID, $this->fieldIDs)) return FALSE;

		// If this field is set to be a primary field, add it
		if($field->primary) $this->addPrimaryFields($field->name);

		// If this field is a primary field, disable it (to prevent the user from munging it)
		if($this->isPrimaryField($field->name)){
			// Set the field to disabled since it's a primary field
			$field->disabled = TRUE;
		}

		// If we're here, then all is well. Save the field and return
		if (!is_empty($field->fieldID)) $this->fieldIDs[$field->name] = $field->fieldID;
		if (!is_empty($field->label)) $this->fieldLabels[$field->name] = $field->label;
		$this->fields[$field->name] = $field;

		// Record the sort-order for this field
		$order                         = !is_empty($field->order) ? $field->order : self::DEFAULT_ORDER;
		$this->fieldOrdering[$order][] = $field->name;

		// Clear any orderedFields cache
		$this->orderedFields = array();

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
	 * Modify a specific field option on the given field
	 *
	 * @param fieldBuilder|string $fieldName
	 * @param string              $option
	 * @param mixed               $value
	 * @return bool
	 */
	public function modifyField($fieldName, $option, $value){
		// If we're changing the order, clear the orderedFields cache
		if($option == 'order') $this->orderedFields = array();

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
	 * Returns the array of fields
	 * @return fieldBuilder[]
	 */
	public function getFields(){
		return $this->fields;
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
	 * Return TRUE if the field has been defined
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasField($name){
		if($name instanceof fieldBuilder) $name = $name->name;
		return isset($this->fields[$name]);
	}

	/**
	 * Returns an array of all defined field names
	 *
	 * @param bool $editStrip
	 * @return array
	 */
	public function listFields($editStrip = NULL){
		return array_keys($this->getSortedFields($editStrip));
	}

	/**
	 * Returns a clean, and fully sorted, array of fields in the order they should appear
	 *
	 * @param bool $editStrip
	 *        TRUE: Only return editStrip
	 *        FALSE: Only return non-editStrip
	 *        NULL: Return all fields (ignore editStrip)
	 * @return fieldBuilder[]
	 */
	public function getSortedFields($editStrip = NULL){
		// Convert the (bool)$editStrip into (string) $storageKey since we can't use bool's as array keys
		if($editStrip === TRUE){
			$storageKey = '_TRUE_';
		}elseif($editStrip === FALSE){
			$storageKey = '_FALSE_';
		}else{
			$storageKey = '_NULL_';
		}

		if(!isset($this->orderedFields[$storageKey])){
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
			foreach ($fieldOrdering as $fieldGroup) {
				foreach ($fieldGroup as $fieldName) {
					$field = $this->getField($fieldName);
					if (in_array($field->type, array('submit','reset','button'))) continue;
					// Skip fields if they don't match $editStrip (null shows all)
					if (TRUE === $editStrip && !$field->showInEditStrip) continue;
					if (FALSE === $editStrip && $field->showInEditStrip) continue;

					$this->orderedFields[$storageKey][] = $fieldName;
				}
			}
		}

		// Return the final, sorted, array of fields
		$returnArray = array();
		if(isset($this->orderedFields[$storageKey]) && sizeof($this->orderedFields[$storageKey])){
			foreach($this->orderedFields[$storageKey] as $fieldName){
				$returnArray[ $fieldName ] = $this->getField($fieldName);
			}
		}
		return $returnArray;
	}

	/**
	 * Add field(s) to the internal list of primary fields
	 * Fields should be specified either as their name or as a fully instantiated fieldBuilder object
	 *
	 * Example Usage:
	 * `$formBuilder->addPrimaryFields('id');`
	 * `$formBuilder->addPrimaryFields('id', 'username');`
	 *
	 * @param string|fieldBuilder ...
	 * @return bool
	 */
	public function addPrimaryFields(){
		$returnStatus = TRUE;

		// Get all the fields passed in and loop on each
		foreach (func_get_args() as $field) {
			// If we got a fieldBuilder, add it (if needed) and then use its name
			if($field instanceof fieldBuilder){
				$fieldName = $field->name;
				if(!$this->hasField($fieldName)) $this->addField($field);
				$field->disabled = TRUE;
				$field = $fieldName;
			}

			// Save the new field to the list
			if ($this->isPrimaryField($field)) {
				errorHandle::newError(__METHOD__."() Field '$field' already set as a primary field!", errorHandle::DEBUG);
				$returnStatus = FALSE;
			} else {
				$this->primaryFields[] = $field;
			}
		}

		return $returnStatus;
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
	 * Checks if a given field is set as a primary field
	 *
	 * @param string $name The name of the field to test
	 * @return bool
	 */
	public function isPrimaryField($name) {
		return in_array($name, $this->primaryFields);
	}
}
