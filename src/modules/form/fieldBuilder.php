<?php


class fieldBuilder{
	/**
	 * The field definition
	 *
	 * @var array
	 */
	private $field;

	/**
	 * The rendered field HTML cache
	 *
	 * @var
	 */
	private $renderedField;

	/**
	 * The rendered field label HTML cache
	 *
	 * @var
	 */
	private $renderedLabel;

	/**
	 * Filepath to form templates
	 *
	 * @var string
	 */
	public $templateDir;

	/**
	 * Local options passed to render
	 *
	 * @var array
	 */
	private $renderOptions;

	/**
	 * Class constructor
	 *
	 * @param array $field
	 */
	public function __construct($field){
		// Make sure we get a sane array
		$this->field = array_merge($this->getDefaultField(), (array)$field);

		// Set the default template directory container path
		$this->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
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
		if (!isset($field['name']) || is_empty($field['name'])) {
			errorHandle::newError(__METHOD__."() Field name required!", errorHandle::DEBUG);
			return FALSE;
		}

		// Make sure any sorting is an int (if there is sorting)
		if (isset($field['order']) && !is_numeric($field['order'])) {
			errorHandle::newError(__METHOD__."() Field order must be integer if provided!", errorHandle::DEBUG);
			return FALSE;
		}

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
		$this->field[$name]  = $val;
		$this->renderedField = NULL;
		$this->renderedLabel = NULL;
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
	 * Returns an array of JS/CSS asset files needed by this field
	 *
	 * This array will follow the convention: assetName => assetFile
	 *
	 * @return array
	 */
	public function getAssets(){
		switch(strtolower($this->field['type'])){
			case 'wysiwyg':
				return array(
					'wysiwyg' => __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'wysiwyg.js'
				);
			case 'multiselect':
				return array(
					'multiSelect' => __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'multiSelect.js'
				);
			default:
				return array();
		}
	}

	/**
	 * Make sure we have a fieldID set
	 * This is done to make sure that <label> and field inputs link to one another
	 * Note: This sets fieldID on this field's data to the generated ID
	 */
	private function ensureFieldID(){
		if (!isset($this->field['fieldID']) || is_empty($this->field['fieldID'])) $this->field['fieldID'] = uniqid('formField_');
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
			if (!is_empty($output)) return $output;
		}

		// Try and load one of our distribution templates (the ones next to this module)
		if (file_exists($this->templateDir.$path)) {
			$output = $routeToFile($this->templateDir.$path, $type);
			if (!is_empty($output)) return $output;
		}

		// All else fails: load the default
		return $path;

	}

	/**
	 * Render as HTML
	 * This renders both the label and field and returns them as a concatenated string
	 *
	 * @param string $template
	 * @param array  $options
	 * @return string
	 */
	public function render($template = NULL, $options = array()){
		// If this field is hidden, then just return the field itself (no label needed)
		if ($this->field['type'] == 'hidden') return $this->renderField($options);

		// If no template is given, just combine the label and field
		if (isnull($template)) return $this->renderLabel($options).$this->renderField($options);

		// Continue with a normal field template
		switch ($this->field['type']) {
			case 'select':
				$type = 'select';
				break;
			case 'multiSelect':
				$type = 'multiselect';
				break;
			case 'wysiwyg':
				$type = 'wysiwyg';
				break;
			case 'text':
			default:
				$type = 'input';
				break;
		}


		// Load the template
		$template = $this->loadTemplate($template, $type);

		// Replace the {label} and {field} tags
		$template = str_replace('{label}', $this->renderLabel($options), $template);
		$template = str_replace('{field}', $this->renderField($options), $template);

		// Return the final, compiled, template
		return $template;
	}

	/**
	 * Render the field and return it
	 *
	 * @param array $options
	 * @return string
	 */
	public function renderField($options = array()){
		$this->ensureFieldID();
		if (is_empty($this->renderedField)) {
			$this->renderOptions = $options;
			switch ($this->field['type']) {
				case 'select':
					$this->renderedField = $this->__renderSelectField();
					break;
				case 'multiSelect':
					$this->renderedField = $this->__renderMultiSelect();
					break;
				case 'wysiwyg':
					$this->renderedField = $this->__renderWYSIWYG();
					break;
				case 'text':
				default:
					$this->renderedField = $this->__renderInputField();
					break;
			}
			$this->renderOptions = NULL;
		}

		return $this->renderedField;
	}

	/**
	 * Render the label and return it
	 *
	 * @param array $options
	 * @return string
	 */
	public function renderLabel($options = array()){
		// If the field is hidden, then we don't need any label at all
		if ($this->field['type'] == 'hidden') return '';

		// Continue for a normal field
		$this->ensureFieldID();
		if (is_empty($this->renderedLabel)) {
			$this->renderOptions = $options;
			$this->renderedLabel = sprintf('<label for="%s"%s>%s</label>',
				$this->getFieldOption('fieldID'),
				$this->buildLabelAttributes(),
				(($label = $this->getFieldOption('label')) ? $label : $this->getFieldOption('name'))
			);
			$this->renderOptions = NULL;
		}

		return $this->renderedLabel;
	}

	/**
	 * [Internal Helper] Render an <input> field
	 *
	 * @return string
	 */
	private function __renderInputField(){
		return sprintf('<input type="%s" value="%s" %s%s>',
			$this->field['type'],
			$this->getFieldOption('value'),
			$this->buildFieldAttributes(),
			(!is_empty($this->field['placeholder']) ? ' placeholder="'.$this->field['placeholder'].'"' : '')
		);
	}

	/**
	 * [Internal Helper] Render a <select> field
	 *
	 * @return string
	 */
	private function __renderSelectField(){
		// If there are multiple values, force multiple to be TRUE (needed for valid HTML5)
		if (is_array($this->field['value'])) $this->field['multiple'] = TRUE;
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
		$attributes = (array)$this->field['labelMetadata'];

		if (!$this->field['disableStyling']) {
			if (!is_empty($this->field['labelCSS'])) $attributes['style'] = $this->field['labelCSS'];
			if (!is_empty($this->field['labelClass'])) $attributes['class'] = $this->field['labelClass'];
		}

		return $this->__buildAttributes($attributes);
	}

	/**
	 * Build the attribute pairs for the field element
	 *
	 * @return string
	 */
	private function buildFieldAttributes(){
		$attributes         = (array)$this->field['fieldMetadata'];
		$attributes['name'] = $this->field['name'];

		if (str2bool($this->getFieldOption('disabled'))) $attributes['bool'][] = 'disabled';
		if (str2bool($this->getFieldOption('readonly'))) $attributes['bool'][] = 'readonly';
		if (str2bool($this->getFieldOption('required'))) $attributes['bool'][] = 'required';
		if (str2bool($this->getFieldOption('multiple'))) $attributes['bool'][] = 'multiple';

		if ($fieldID = $this->getFieldOption('fieldID')) $attributes['id'] = $fieldID;
		if (!$this->field['disableStyling']) {
			if ($fieldCSS = $this->getFieldOption('fieldCSS')) $attributes['style'] = $fieldCSS;
			if ($fieldClass = $this->getFieldOption('fieldClass')) $attributes['class'] = $fieldClass;
		}

		return $this->__buildAttributes($attributes);
	}

	private function getFieldOption($name){
		if (isset($this->renderOptions[$name]) && !is_empty($this->renderOptions[$name])) return $this->renderOptions[$name];
		if (isset($this->field[$name]) && !is_empty($this->field[$name])) return $this->field[$name];
		return NULL;
	}

	/**
	 * [Helper] Takes an array and builds the attributes needed for an HTML tag
	 *
	 * @param array $attributes
	 * @return string
	 */
	private function __buildAttributes($attributes){
		if (isset($this->renderOptions)) {
			$addlMetadata = array_diff(array_keys($this->renderOptions), array_keys($this->getDefaultField()));
			foreach ($addlMetadata as $key) {
				$attributes[$key] = $this->renderOptions[$key];
			}
		}

		$attrPairs = array();
		foreach ($attributes as $key => $val) {
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
			$key          = isset($this->field['linkedTo']['key']) ? $this->field['linkedTo']['key'] : NULL;
			$field        = isset($this->field['linkedTo']['field']) ? $this->field['linkedTo']['field'] : NULL;
			$table        = isset($this->field['linkedTo']['table']) ? $this->field['linkedTo']['table'] : NULL;
			$order        = isset($this->field['linkedTo']['order']) ? $this->field['linkedTo']['order'] : NULL;
			$where        = isset($this->field['linkedTo']['where']) ? $this->field['linkedTo']['where'] : NULL;
			$limit        = isset($this->field['linkedTo']['limit']) ? $this->field['linkedTo']['limit'] : NULL;
			$sql          = isset($this->field['linkedTo']['sql']) ? $this->field['linkedTo']['sql'] : NULL;

			// Get the db connection we'll be talking to
			$db = db::getInstance()->$dbConnection;

			// Build the SQL (if needed)
			if (!isset($sql)) {
				if (!isset($key) || !isset($field) || !isset($table)) {
					errorHandle::newError(__METHOD__."() Using linkedTo but missing key, field, and/or table params", errorHandle::DEBUG);
					return '';
				}
				$sql = sprintf('SELECT `%s`, `%s` FROM `%s`',
					$db->escape($key),
					$db->escape($field),
					$db->escape($table));
				if (isset($where) && !empty($where)) $sql .= " WHERE $where";
				$sql .= (isset($order) && !empty($order))
					? " ORDER BY $order"
					: " ORDER BY ".$db->escape($field)." ASC";
				if (isset($limit) && !empty($limit)) $sql .= " LIMIT $limit";
			}

			// Run the SQL
			$sqlResult = $db->query($sql);

			// Format the result into a usable array
			$options = array();
			while ($row = $sqlResult->fetch()) {
				$key           = array_shift($row); // The key is always the 1st col
				$val           = array_shift($row); // The key is always the 2nd col
				$options[$key] = $val;
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
