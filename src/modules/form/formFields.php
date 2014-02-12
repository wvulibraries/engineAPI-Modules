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
		// If we got an array, make it a fieldBuilder
		if (is_array($field)) $field = fieldBuilder::createField($field);

		// Make sure we're working with a fieldBuilder
		if (!($field instanceof fieldBuilder)) return FALSE;

		// Make sure the field name is unique
		if (isset($this->fields[$field->name])) return FALSE;

		// If there's a label, make sure it's unique
		if (!is_empty($field->label) && in_array($field->label, $this->fieldLabels)) return FALSE;

		// If there's a field ID, make sure it's unique
		if (!is_empty($field->fieldID) && in_array($field->fieldID, $this->fieldIDs)) return FALSE;

		// If we're here, then all is well. Save the field and return
		if (!is_empty($field->fieldID)) $this->fieldIDs[$field->name] = $field->fieldID;
		if (!is_empty($field->label)) $this->fieldLabels[$field->name] = $field->label;
		$this->fields[$field->name] = $field;

		// Record the sort-order for this field
		$order                         = !is_empty($field->order) ? $field->order : self::DEFAULT_ORDER;
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
	public function getSortedFields($editStrip = NULL){
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

}