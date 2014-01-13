<?php


class fieldBuilder{
	private $field;
	private $renderedHTML;

	/**
	 * Class constructor
	 *
	 * @param array $field
	 */
	public function __construct($field){
		// Make sure we get a sane array
		$field       = array_merge($this->getDefaultField(), (array)$field);
		$this->field = $field;
	}

	/**
	 * [Factory] Create a fieldBuilder object. (Returns FALSE on error)
	 *
	 * @param array|string $field
	 * @return bool|fieldBuilder
	 */
	public static function createField($field){
		// If given a string, make it a valid array
		if (is_string($field)) $field = array('name' => $field);
		// Make sure we got a field name
		if (!isset($field['name']) || isempty($field['name'])) return FALSE;

		// Return a new fieldBuilder object
		return new self($field);
	}

	/**
	 * [Magic Method] Gets a given field option
	 *
	 * @param string $var
	 * @return mixed
	 */
	public function __get($var){
		return isset($this->field[$var])
			? $this->field[$var]
			: NULL;
	}

	/**
	 * [Magic Method] Sets a given field option
	 *
	 * @param string $name
	 * @param mixed  $val
	 */
	public function __set($name, $val){
		$this->field[$name] = $val;
		$this->renderedHTML = NULL;
	}

	/**
	 * [Magic Method] Render as HTML
	 *
	 * @see self::render()
	 * @return string
	 */
	public function __toString(){
		return $this->render();
	}

	/**
	 * [Magic Method] Defines which object variable(s) should be saved when calling serialize()
	 *
	 * @return array
	 */
	public function __sleep(){
		return array('field');
	}

	/**
	 * Render as HTML
	 *
	 * @return string
	 */
	public function render(){
		if (isempty($this->renderedHTML)) {
			switch ($this->field['type']) {
				case 'select':
					$this->renderedHTML = $this->__renderSelectField();
					break;
				case 'multiSelect':
					$this->renderedHTML = $this->__renderMultiSelect();
					break;
				case 'wysiwyg':
					$this->renderedHTML = $this->__renderWYSIWYG();
					break;
				case 'text':
				default:
					$this->renderedHTML = $this->__renderInputField();
					break;
			}
		}

		return $this->renderedHTML;
	}

	/**
	 * [Internal Helper] Render an <input> field
	 *
	 * @return string
	 */
	private function __renderInputField(){
		return sprintf('<input type="%s" value="%s" %s%s>',
			$this->field['type'],
			$this->field['value'],
			$this->buildFieldAttributes(),
			(!isempty($this->field['placeholder']) ? ' placeholder="' . $this->field['placeholder'] . '"' : '')
		);
	}

	/**
	 * [Internal Helper] Render a <select> field
	 *
	 * @return string
	 */
	private function __renderSelectField(){
		// If there are multiple values, force multiple to be TRUE (needed for valid HTML5)
		if(is_array($this->field['value'])) $this->field['multiple'] = TRUE;
		// Return the built tag
		return sprintf('<select %s>%s</select>',
			$this->buildFieldAttributes(),
			$this->buildSelectOptions());
	}

	/**
	 * [Internal Helper] Render a Multi-select box
	 *
	 * @return string
	 */
	private function __renderMultiSelect(){
		return 'multiSelect';
	}

	/**
	 * [Internal Helper] Render a WYSIWYG editor
	 *
	 * @return string
	 */
	private function __renderWYSIWYG(){
		return 'wysiwyg';
	}


	/**
	 * Returns an array with all default field options
	 *
	 * @return array
	 */
	private function getDefaultField(){
		return array(
			'disabled'       => FALSE,
			'size'           => 40,
			'duplicates'     => FALSE,
			'optional'       => FALSE,
			'type'           => 'text',
			'readonly'       => FALSE,
			'value'          => '',
			'linkedTo'       => array(),
			'validate'       => NULL,
			'placeholder'    => '',
			'help'           => array(),
			'dragDrop'       => FALSE,
			'label'          => '',
			'labelMetadata'  => array(),
			'fieldMetadata'  => array(),
			'required'       => FALSE,
			'disableStyling' => FALSE,
			'fieldCSS'       => '',
			'fieldClass'     => '',
			'fieldID'        => '',
			'labelCSS'       => '',
			'labelClass'     => '',
			'labelID'        => '',
			'selectValues'   => array(),
			'multiple'       => FALSE
		);
	}

	/**
	 * Build the attribute pairs for the label element
	 *
	 * @return string
	 */
	private function buildLabelAttributes(){

	}

	/**
	 * Build the attribute pairs for the field element
	 *
	 * @return string
	 */
	private function buildFieldAttributes(){
		$attrs         = (array)$this->field['fieldMetadata'];
		$attrs['name'] = $this->field['name'];

		if ($this->field['disabled']) $attrs['bool'][] = 'disabled';
		if ($this->field['readonly']) $attrs['bool'][] = 'readonly';
		if ($this->field['required']) $attrs['bool'][] = 'required';
		if ($this->field['multiple']) $attrs['bool'][] = 'multiple';

		if (!isempty($this->field['fieldID'])) $attrs['id'] = $this->field['fieldID'];
		if (!$this->field['disableStyling']) {
			if (!isempty($this->field['fieldCSS'])) $attrs['style'] = $this->field['fieldCSS'];
			if (!isempty($this->field['fieldClass'])) $attrs['class'] = $this->field['fieldClass'];
		}

		$attrPairs = array();
		foreach ($attrs as $key => $val) {
			if ($key == 'data') {
				foreach ($val as $data_name => $data_val) {
					if (is_bool($data_val)) $data_name = bool2str($data_val, TRUE);
					$attrPairs[] = sprintf('data-%s="%s"', $data_name, $data_val);
				}
			} elseif ($key == 'bool') {
				foreach ($val as $flag) {
					$attrPairs[] = $flag;
				}
			} else {
				if (is_bool($val)) $val = bool2str($val, TRUE);
				$attrPairs[] = sprintf('%s="%s"', $key, $val);
			}
		}

		return implode(' ', $attrPairs);
	}

	/**
	 * Built the <option>'s for the select field
	 *
	 * @return string
	 */
	private function buildSelectOptions(){
		$output = '';

		if (sizeof($this->field['selectValues'])) {
			$options = $this->field['selectValues'];
		} elseif (isset($this->field['linkedTo'])) {

			$dbConnection = isset($this->field['linkedTo']['dbConnection']) ? $this->field['linkedTo']['dbConnection'] : 'appDB';
			$key          = isset($this->field['linkedTo']['key'])          ? $this->field['linkedTo']['key']          : NULL;
			$field        = isset($this->field['linkedTo']['field'])        ? $this->field['linkedTo']['field']        : NULL;
			$table        = isset($this->field['linkedTo']['table'])        ? $this->field['linkedTo']['table']        : NULL;
			$order        = isset($this->field['linkedTo']['order'])        ? $this->field['linkedTo']['order']        : NULL;
			$where        = isset($this->field['linkedTo']['where'])        ? $this->field['linkedTo']['where']        : NULL;
			$limit        = isset($this->field['linkedTo']['limit'])        ? $this->field['linkedTo']['limit']        : NULL;
			$sql          = isset($this->field['linkedTo']['sql'])          ? $this->field['linkedTo']['sql']          : NULL;

			// Get the db connection we'll be talking to
			$db = db::getInstance()->$dbConnection;

			// Build the SQL (if needed)
			if(!isset($sql)){
				if(!isset($key) || !isset($field) || !isset($table)){
					errorHandle::newError(__METHOD__."() Using linkedTo but missing key, field, and/or table params", errorHandle::DEBUG);
					return '';
				}
				$sql = sprintf('SELECT `%s`, `%s` FROM `%s`',
					$db->escape($key),
					$db->escape($field),
					$db->escape($table));
				if(isset($where) && !empty($where)) $sql .= " WHERE $where";
				$sql .= (isset($order) && !empty($order))
					? " ORDER BY $order"
					: " ORDER BY ".$db->escape($field)." ASC";
				if(isset($limit) && !empty($limit)) $sql .= " LIMIT $limit";
			}

			// Run the SQL
//			echo $sql;
			$sqlResult = $db->query($sql);

			// Format the result into a usable array
			$options = array();
			while($row = $sqlResult->fetch()){
				$key = array_shift($row); // The key is always the 1st col
				$val = array_shift($row); // The key is always the 2nd col
				$options[ $key ] = $val;
			}

		} else {
			// No options
			return '';
		}

		// Loop, and build the options
		foreach ($options as $key => $val) {
			$selected = in_array($key, (array)$this->field['value'])
				? ' selected'
				: '';
			$output .= sprintf('<option value="%s"%s>%s</option>',
				$key,
				$selected,
				$val);
		}
		return $output;

	}

}
