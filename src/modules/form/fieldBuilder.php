<?php

class fieldBuilder{
	/** @var array An array with all default field options */
	private static $fieldDefaults = array(
		'disabled'        => FALSE,
		'size'            => 40,
		'duplicates'      => FALSE,
		'type'            => 'text',
		'readonly'        => FALSE,
		'value'           => '',
		'linkedTo'        => array(),
		'validate'        => NULL,
		'placeholder'     => '',
		'help'            => array(),
		'dragDrop'        => FALSE,
		'label'           => '',
		'labelMetadata'   => array(),
		'fieldMetadata'   => array(),
		'required'        => FALSE,
		'disableStyling'  => FALSE,
		'fieldCSS'        => '',
		'fieldClass'      => '',
		'fieldID'         => '',
		'labelCSS'        => '',
		'labelClass'      => '',
		'labelID'         => '',
		'options'         => array(),
		'multiple'        => FALSE,
		'showInEditStrip' => FALSE,
		'primary'         => FALSE,
	);

	/**
	 * @var array The field definition
	 */
	private $field;

	/**
	 * @var string Filepath to form templates
	 */
	public $templateDir;

	/**
	 * @var array Local options passed to render
	 */
	private $renderOptions;

	/**
	 * @var string Internal render type (controls which method will render the field)
	 */
	private $renderType;

	/**
	 * Class constructor
	 *
	 * @param array $field
	 */
	public function __construct($field){
		// Make sure we get a sane array
		$this->field = array_merge(self::$fieldDefaults, (array)$field);

		// Normalize field definitions
		if(isset($this->field['type'])) $this->field['type'] = trim(strtolower($this->field['type']));

		// Set the default template directory container path
		$this->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
	}

	/**
	 * Return a SQL-ready string snippet for this field
	 * @return string
	 */
	function toSqlSnippet(){
		return "`{$this->name}`=?";
	}

	/**
	 * Returns TRUE if this is a 'special' field like a button
	 * @return bool
	 */
	function isSpecial(){
		return in_array($this->type, array('submit','reset','button'));
	}

	/**
	 * Returns TRUE is this is a 'system' field that begins with '__'
	 * @return bool
	 */
	function isSystem(){
		return (0 === strpos($this->name, '__'));
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
		$this->field[$name] = $val;
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
		$assets = array();

		// Add assets for specific field types
		switch($this->field['type']){
			case 'wysiwyg':
				return array(
					// __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'ckeditor'.DIRECTORY_SEPARATOR.'ckeditor.js',
					// __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'wysiwyg.js',
				);
			case 'multiselect':
				$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'multi-select.css';
				$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'multi-select.js';
		}

		// Add assets for specific field options
		$help = $this->field['help'];
		if(sizeof($help)){
			switch(@$help['type']){
				case 'modal':
					$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'jquery.leanModal.min.js';
					break;
			}
		}

		return $assets;
	}

	/**
	 * Returns the applicable option given the current scope
	 *
	 * Looks in renderOptions first, then falls back to the field definition.
	 * All else fails, return NULL
	 *
	 * @param string $name
	 * @return mixed
	 */
	private function getFieldOption($name){
		if (isset($this->renderOptions[$name])) return $this->renderOptions[$name];
		if (isset($this->field[$name])) return $this->field[$name];
		return NULL;
	}

	/**
	 * Make sure we have a fieldID set
	 * This is done to make sure that <label> and field inputs link to one another
	 * Note: This sets fieldID on this field's data to the generated ID
	 */
	private function ensureFieldID(){
		if (!isset($this->field['fieldID']) || is_empty($this->field['fieldID'])) $this->field['fieldID'] = uniqid();
	}

	/**
	 * Locate and return the file contents of the requested template
	 *
	 * If $path and $type point to a valid file on the file system, then load and return it
	 * Else, assume $path contains the template text itself (it's a blob)
	 *
	 * @param string $path The base path to look in
	 * @return string
	 */
	private function loadTemplate($path){
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
			$output = $routeToFile($path, $this->renderType);
			if (!is_empty($output)) return $output;
		}

		// Try and load one of our distribution templates (the ones next to this module)
		if (file_exists($this->templateDir.$path)) {
			$output = $routeToFile($this->templateDir.$path, $this->renderType);
			if (!is_empty($output)) return $output;
		}

		// All else fails: load the default
		return $path;

	}

	/**
	 * Sets the 'rendered' value for this field
	 *
	 * This is used later if the field is read-only to get back to the original value (ignoring any end-user munging)
	 *
	 * @param $value
	 */
	public function setRenderedValue($value){
		$this->field['renderedValue'] = $value;
	}

	/**
	 * Determine the internal field type
	 *
	 * This method will map the external field type to our internal type which controls
	 * how the field is actually rendered. A good example of this would be the HTML5 types
	 * which area really just <input> fields
	 */
	private function determineInternalFieldType(){
		// Only continue if we don't know
		if(!isset($this->renderType)){
			switch ($this->field['type']) {
				case 'radio':
				case 'checkbox':
				case 'multiselect':
				case 'plaintext':
				case 'password':
				case 'wysiwyg':
				case 'file':
				case 'textarea':
					$type = $this->field['type'];
					break;
				case 'select':
				case 'dropdown':
					$type = 'select';
				case 'bool':
				case 'boolean':
					$type = 'boolean';
					break;
				case 'text':
				case 'string':
				default:
					$type = 'input';
					break;
			}
			$this->renderType = $type;
		}
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
		// If no template is given, just combine the label and field
		if (isnull($template)) return $this->renderLabel($options).$this->renderField($options).$this->renderHelp();

		// Determine the internal type and load the template
		$this->determineInternalFieldType();
		$template = $this->loadTemplate($template);

		// Replace the {label} and {field} tags
		$template = str_replace('{label}', $this->renderLabel($options), $template);
		$template = str_replace('{field}', $this->renderField($options), $template);
		$template = str_replace('{help}',  $this->renderHelp(),          $template);

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
		// Determine the internal type
		$this->determineInternalFieldType();

		// Determine the rendering function
		$func = array($this, '__render_'.$this->field['type']);
		if (!is_callable($func)) $func = array($this, '__render_input');

		if (isset($options['display'])) unset($options['display']);

		// Render time!
		$this->renderOptions = $options;
		$this->setRenderedValue($this->getFieldOption('value'));
		$output = call_user_func($func);
		$this->renderOptions = NULL;

		return $output;
	}

	/**
	 * Render the label and return it
	 *
	 * @param array $options
	 * @return string
	 */
	public function renderLabel($options = array()){
		// If this field does not require a label, don't render one!
		$ignoredTypes = array('hidden','button','submit','reset','plaintext');
		if (in_array($this->field['type'], $ignoredTypes)) return '';

		// Continue for a normal field
		$this->ensureFieldID();

		if (isset($options['name'])) unset($options['name']);
		if (isset($options['display'])) unset($options['display']);

		$this->renderOptions = $options;
		$output = sprintf('<label for="%s"%s>%s</label>',
				$this->getFieldOption('fieldID'),
				$this->buildLabelAttributes(),
				(($label = $this->getFieldOption('label')) ? $label : $this->getFieldOption('name'))
			);
		$this->renderOptions = NULL;

		return $output;
	}

	/**
	 * Render a help icon and return it
	 *
	 * @return string
	 */
	public function renderHelp(){
		// No help, then no render
		if(!sizeof($this->field['help'])) return '';

		// Okay, render the help
		$help = $this->field['help'];
		switch(@$help['type']) {
			case 'modal':
				if(isset($help['text'])){
					$value = $help['text'];
				}elseif(isset($help['path'])){
					$value = file_get_contents($help['path']);
				}elseif(isset($help['url'])){
					// Get the source of the URL page
					$value = file_get_contents($help['url']);
					// If the page is plain-text, wrap the source in <pre> tags to preserve formatting
					$headers = get_headers($help['url'], 1);
					if($headers['Content-Type'] == 'text/plain') $value = "<pre>$value</pre>";
				}else{
					errorHandle::newError(__METHOD__."() Warning: No valid source provided for modal! (Please use text, url, or path)", errorHandle::DEBUG);
					$value = '';
				}

				return sprintf('<a href="#modalWindow_%s" id="modalTrigger_%s"><i class="icon-help"></i></a><div id="modalWindow_%s" class="modalWindow">%s</div><script>$(\'#modalTrigger_%s\').leanModal()</script>',
					$this->getFieldOption('fieldID'),
					$this->getFieldOption('fieldID'),
					$this->getFieldOption('fieldID'),
					$value,
					$this->getFieldOption('fieldID')
				);

			case 'newWindow':
				return sprintf('<a href="%s" target="_blank"><i class="icon-help"></i></a>', $help['url']);

			case 'hover':
			case 'tooltip':
			default:
				return sprintf('<i class="icon-help" title="%s"></i>',
					isset($help['text']) ? htmlSanitize($help['text'])  : ''
				);
		}
	}

	/**
	 * [Render Helper] Render an <input> field
	 * @return string
	 */
	private function __render_input(){
		return sprintf('<input type="%s" value="%s" %s%s>',
			$this->field['type'],
			$this->getFieldOption('value'),
			$this->buildFieldAttributes(),
			(!is_empty($this->getFieldOption('placeholder')) ? ' placeholder="'.$this->getFieldOption('placeholder').'"' : '')
		);
	}

	/**
	 * [Render Helper] Render a <select> field
	 * @return string
	 */
	private function __render_select(){
		// If there are multiple values, force multiple to be TRUE (needed for valid HTML5)
		if (is_array($this->getFieldOption('value'))) $this->renderOptions['multiple'] = TRUE;

		// If this is a multiple select, make the name array-friendly
		if($this->getFieldOption('multiple')) $this->renderOptions['name'] = $this->getFieldOption('name').'[]';

		// Return the built tag
		return sprintf('<select %s>%s</select>',
			$this->buildFieldAttributes(),
			$this->buildSelectOptions());
	}

	/**
	 * [Render Helper] Render a Multi-select box
	 * @return string
	 */
	private function __render_multiselect(){
		$this->renderOptions['multiple'] = TRUE;
		$selectBox = $this->__render_select();
		$scriptTag = sprintf("<script>$('#%s').multiSelect()</script>",
			$this->getFieldOption('fieldID')
		);
		return $selectBox.$scriptTag;
	}

	/**
	 * [Render Helper] Render a WYSIWYG editor
	 * @return string
	 */
	private function __render_wysiwyg(){
		//		$fieldClass = $this->getFieldOption('class');
//		if(FALSE === strpos('ckeditor', $fieldClass)) $this->renderOptions['class'] = "$fieldClass ckeditor";

		$output = $this->__render_textarea();
//		$output .= sprintf("<script>CKEDITOR.replace(%s, { customConfig: '' } );</script>", $this->getFieldOption('fieldID'));
		return $output;
	}

	/**
	 * [Render Helper] Render a radio field
	 * @return string
	 */
	private function __render_radio(){
		$output = '';

		// Make sure there's options
		$options = $this->getFieldOption('options');
		if(!$options){
			errorHandle::newError(__METHOD__.'() You must provide options to render a radio field!', errorHandle::DEBUG);
			return '';
		}

		foreach($options as $value => $label){
			// Append the value to fieldID for uniqueness
			$this->renderOptions['fieldID'] = $this->field['fieldID']."_".str_replace(' ','_',$value);
			// Render time
			$output .= sprintf('<label class="radioLabel"><input type="radio" value="%s" %s%s> %s</label>',
				$value,
				($this->getFieldOption('value') == $value ? ' checked ' : ''),
				$this->buildFieldAttributes(),
				htmlSanitize($label)
			);
		}
		return "<div class='radioGroup'>$output</div>";
	}

	/**
	 * [Render Helper] Render a checkbox field
	 * @return string
	 */
	private function __render_checkbox(){
		$output = '';

		// Make the given values an array (may be a CSV)
		$values = (array)$this->getFieldOption('value');
		if(is_string($values)) $values = explode(',', $values);

		// Make sure there's options
		$options = $this->getFieldOption('options');
		if(!$options){
			errorHandle::newError(__METHOD__.'() You must provide options to render a checkbox field!', errorHandle::DEBUG);
			return '';
		}

		if(sizeof($options) > 1){
			// Make the name checkbox array friendly
			$this->renderOptions['name'] = $this->field['name'].'[]';

			foreach($options as $value => $label){
				// Append the value to fieldID for uniqueness
				$this->renderOptions['fieldID'] = $this->field['fieldID']."_".str_replace(' ','_',$value);

				// Render time
				$output .= sprintf('<label class="checkboxLabel"><input type="checkbox" value="%s" %s%s> %s</label>',
					$value,
					(in_array($value, $values) ? ' checked ' : ''),
					$this->buildFieldAttributes(),
					htmlSanitize($label));
			}
		}else{
			$keys = array_keys($options);
			return sprintf('<input type="checkbox" value="%s" %s%s>',
				$keys[0],
				(in_array($keys[0], $values) ? ' checked ' : ''),
				$this->buildFieldAttributes());
		}

		return $output;
	}

	/**
	 * [Render Helper] Render a plaintext field
	 * @return string
	 */
	private function __render_plaintext(){
		return $this->getFieldOption('value');
	}

	/**
	 * [Render Helper] Render a password field
	 * @return string
	 */
	private function __render_password(){
		// Render the password field
		if(!isset($this->renderOptions['placeholder'])) $this->renderOptions['placeholder'] = 'Password';
		$output = $this->__render_input();

		// Render the password confirmation field
		$this->renderOptions['name']    = $this->getFieldOption('name').'_confirm';
		$this->renderOptions['fieldID'] = $this->getFieldOption('fieldID').'_confirm';
		$this->renderOptions['placeholder'] = $this->getFieldOption('placeholder').' Confirmation';
		$output .= $this->__render_input();

		return $output;
	}

	/**
	 * [Render Helper] Render a textarea field
	 * @return string
	 */
	private function __render_textarea(){
		return sprintf('<textarea %s%s>%s</textarea>',
			$this->buildFieldAttributes(),
			(!is_empty($this->field['placeholder']) ? ' placeholder="'.$this->field['placeholder'].'"' : ''),
			$this->getFieldOption('value')
		);
	}

	/**
	 * [Render Helper] Render a boolean field
	 * @return string
	 */
	private function __render_boolean(){
		$options = $this->getFieldOption('options');
		$type    = isset($options['type']) ? $options['type'] : 'select';

		// Blank the options
		$this->renderOptions['options'] = array();

		// Determine labels
		$no  = isset($options['labels']) ? array_shift($options['labels']) : 'No';
		$yes = isset($options['labels']) ? array_shift($options['labels']) : 'Yes';

		switch(trim(strtolower($type))){
			case 'check':
			case 'checkbox':
				$this->renderOptions['options'][1] = 'Yes';
				return $this->__render_checkbox();

			case 'radio':
				$this->renderOptions['options'][0] = $no;
				$this->renderOptions['options'][1] = $yes;
				return $this->__render_radio();

			case 'select':
			default:
				if(isset($options['blankOption']) && $options['blankOption'] !== FALSE){
					$this->renderOptions['options'][''] = ($options['blankOption'] !== TRUE)
						? $options['blankOption']
						: '';

				}
				$this->renderOptions['options'][0] = $no;
				$this->renderOptions['options'][1] = $yes;
				return $this->__render_select();
		}
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
		$attributes['name'] = $this->getFieldOption('name');

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

	/**
	 * [Helper] Takes an array and builds the attributes needed for an HTML tag
	 *
	 * @param array $attributes
	 * @return string
	 */
	private function __buildAttributes($attributes){
		if (isset($this->renderOptions)) {
			$addlMetadata = array_diff(array_keys($this->renderOptions), array_keys(self::$fieldDefaults));
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

		if (sizeof($this->getFieldOption('options'))) {
			$options = $this->getFieldOption('options');
		} elseif (sizeof($this->getFieldOption('linkedTo'))) {
			$linkedTo         = $this->getFieldOption('linkedTo');
			$dbConnection     = isset($linkedTo['dbConnection'])     ? $linkedTo['dbConnection']     : 'appDB';
			$foreignTable     = isset($linkedTo['foreignTable'])     ? $linkedTo['foreignTable']     : NULL;
			$foreignKey       = isset($linkedTo['foreignKey'])       ? $linkedTo['foreignKey']       : 'ID';
			$foreignLabel     = isset($linkedTo['foreignLabel'])     ? $linkedTo['foreignLabel']     : NULL;
			$foreignOrder     = isset($linkedTo['foreignOrder'])     ? $linkedTo['foreignOrder']     : NULL;
			$foreignWhere     = isset($linkedTo['foreignWhere'])     ? $linkedTo['foreignWhere']     : NULL;
			$foreignLimit     = isset($linkedTo['foreignLimit'])     ? $linkedTo['foreignLimit']     : NULL;
			$foreignSQL       = isset($linkedTo['foreignSQL'])       ? $linkedTo['foreignSQL']       : NULL;
			$linkTable        = isset($linkedTo['linkTable'])        ? $linkedTo['linkTable']        : NULL;
			$linkForeignField = isset($linkedTo['linkForeignField']) ? $linkedTo['linkForeignField'] : NULL;
			$linkLocalField   = isset($linkedTo['linkLocalField'])   ? $linkedTo['linkLocalField']   : NULL;

			// Get the db connection we'll be talking to
			$db = db::getInstance()->$dbConnection;

			// Build the SQL (if needed)
			if (!isset($foreignSQL)) {
				if (!isset($foreignKey) || !isset($foreignLabel) || !isset($foreignTable)) {
					errorHandle::newError(__METHOD__."() Using linkedTo but missing key, field, and/or table params", errorHandle::DEBUG);
					return '';
				}
				$foreignSQL = sprintf('SELECT `%s`, `%s` FROM `%s`',
					$db->escape($foreignKey),
					$db->escape($foreignLabel),
					$db->escape($foreignTable));
				if (isset($foreignWhere) && !empty($foreignWhere)) $foreignSQL .= " WHERE $foreignWhere";
				$foreignSQL .= (isset($foreignOrder) && !empty($foreignOrder))
					? " ORDER BY $foreignOrder"
					: " ORDER BY ".$db->escape($foreignLabel)." ASC";
				if (isset($foreignLimit) && !empty($foreignLimit)) $foreignSQL .= " LIMIT $foreignLimit";
			}

			// Run the SQL
			$sqlResult = $db->query($foreignSQL);

			// Format the result into a usable array
			$options = array();
			while ($row = $sqlResult->fetch()) {
				$foreignKey           = array_shift($row); // The key is always the 1st col
				$val           = array_shift($row); // The key is always the 2nd col
				$options[$foreignKey] = $val;
			}

		} else {
			// No options
			return '';
		}

		// Loop, and build the options
		foreach ($options as $foreignKey => $val) {
			$selected = in_array($foreignKey, (array)$this->field['value'], TRUE)
				? ' selected'
				: '';
			$output .= sprintf('<option value="%s"%s>%s</option>',
				$foreignKey,
				$selected,
				htmlSanitize($val));
		}
		return $output;

	}

}
