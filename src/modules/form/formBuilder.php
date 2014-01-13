<?php


class formBuilder implements Countable {
    private $fields = array();
    private $fieldLabels = array();
    private $fieldIDs = array();

    public function __construct(){}

	/**
	 * Returns the number of fields
	 * @return int
	 */
	public function count(){
		return sizeof($this->fields);
	}

	/**
	 * Remove all fields, and reset back to initial state
	 */
	public function reset(){
		$this->fields = array();
		$this->fieldLabels = array();
		$this->fieldIDs = array();
	}

    /**
     * Add a field
     * @param array|fieldBuilder $field
     * @return bool
     */
    public function addField($field){
        // If we got an array, make it a fieldBuilder
        if(is_array($field)) $field = fieldBuilder::createField($field);

        // Make sure we're working with a fieldBuilder
        if(!($field instanceof fieldBuilder)) return FALSE;

        // Make sure the field name is unique
        if(isset($this->fields[$field->name])) return FALSE;

        // If there's a label, make sure it's unique
		if(!isempty($field->label) && in_array($field->label, $this->fieldLabels)) return FALSE;

        // If there's a field ID, make sure it's unique
        if(!isempty($field->fieldID) && in_array($field->fieldID, $this->fieldIDs)) return FALSE;

		// If we're here, then all is well. Save the field and return
		if(!isempty($field->fieldID)) $this->fieldIDs[]    = $field->fieldID;
		if(!isempty($field->label))   $this->fieldLabels[] = $field->label;
        $this->fields[$field->name] = $field;
        return TRUE;
    }

    /**
     * Remove a field
     * @param string $fieldName
     * @return bool
     */
    public function removeField($fieldName){
        if(isset($this->fields[$fieldName])){
            unset($this->fields[$fieldName]);
            return TRUE;
        }else{
            return FALSE;
        }
    }

    /**
     * Modify a specific field option on the given field
     * @param fieldBuilder|string $fieldName
     * @param string $option
     * @param mixed $value
     * @return bool
     */
    public function modifyField($fieldName,$option,$value){
        if($fieldName instanceof fieldBuilder){
            $fieldName->$option = $value;
            return TRUE;
        }elseif(is_string($fieldName) && isset($this->fields[$fieldName])){
            $this->fields[$fieldName]->$option = $value;
            return TRUE;
        }else{
            return FALSE;
        }
    }

    /**
     * Calls modifyField() on each field
     *
     * @see self::modifyField()
     * @param string $option
     * @param mixed $value
     * @return bool
     */
    public function modifyAllFields($option,$value){
        foreach($this->fields as $field){
            if(!$this->modifyField($field, $option, $value)) return FALSE;
        }
        return TRUE;
    }

	/**
	 * Return TRUE if the given field name has been defined
	 * @param string $name
	 * @return bool
	 */
	public function fieldExists($name){
		return isset($this->fields[$name]);
	}

    /**
     * Returns the requested field or NULL if no field defined
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
     * @return array
     */
    public function listFields(){
        return array_keys($this->fields);
    }
}