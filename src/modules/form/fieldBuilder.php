<?php

/**
 * Class fieldBuilder
 *
 * Field Options:
 *  - blankOption         [bool|str] Include a blank option on 'select' field. If it's a string, will be the label for the blank options (default: false)
 *  - disabled            [bool]     Disable the field
 *  - disableStyling      [bool]     If true, then ignores all CSS styling (ie fieldClass, fieldCSS, labelClass, & fieldCSS) (default: falsE)
 *  - duplicates          [bool]     Allow duplicated (default: true)
 *  - fieldClass          [str]      CSS Classes to add to the field
 *  - fieldCSS            [str]      CSS Style to add to the field
 *  - fieldID             [str]      id attribute for the field
 *  - fieldMetadata       [array]    Array of key->value pairs to be added to the field through data-* attributes
 *  - hash                [str]      The mhash algorithm to use for password fields (default: sha512)
 *  - help                [array]    Array of field help options
 *     - type             [str]      The type of help: modal, newWindow, hover, tooltip (default: tooltip)
 *     - text             [str]      Plaintext to display
 *     - url              [str]      URL of content
 *     - file             [str]      Local file to pull content from
 *  - label               [str]      The label for the field (default: {} to field's name)
 *  - labelClass          [str]      CSS Classes to add to the label
 *  - labelCSS            [str]      CSS Classes to add to the label
 *  - labelID             [str]      id attribute for the label
 *  - labelMetadata       [array]    Array of key->value pairs to be added to the label through data-* attributes
 *  - linkedTo            [array]    Array of metadata denoting either a one-to-many or many-to-many relationship
 *     - foreignTable     [str]      The table where the values for this field live
 *     - foreignKey       [str]      The column on the foreignTable which contains the value
 *     - foreignLabel     [str]      The column on the foreignTable which contains the label
 *     - foreignOrder     [str]      Optional ORDER BY clause (default: '{foreignLabel} ASC')
 *     - foreignWhere     [str]      Optional WHERE clause
 *     - foreignLimit     [str]      Optional LIMIT clause
 *     - foreignSQL       [str]      Option raw SELECT SQL to be used. (1st column is treated as foreignKey and 2nd as foreignLabel)
 *     - linkTable        [str]      many-to-many: Linking table name
 *     - linkLocalField   [str]      many-to-many: Linking table column where the local key lives
 *     - linkForeignField [str]      many-to-many: Linking table column where the foreign key lives
 *  - multiple            [bool]     Sets 'multiple' on a select field (default: false)
 *  - options             [array]    Array of field options for select, checkbox, radio, and boolean
 *  - placeholder         [str]      Text to put in field's placeholder="*" attribute
 *  - primary             [bool]     Sets the field as a primary field (multiple primary fields are allowed*) (default: false)
 *  - readonly            [bool]     Sets the field to be read-only (default: false)
 *  - required            [bool]     Sets the field as required (default: false)
 *  - showIn              [array]    Show/Hide the field in specified forms (default: array of all types)
 *  - type                [str]      The type of field (see list of field types below)
 *  - validate            [str]      The validate method to check the value against
 *  - value               [str]      The initial value for this field
 *
 * Field types:
 *  - bool        Alias for 'boolean'
 *  - boolean     Boolean (Yes/No) field
 *    - options
 *      - type    [string] Type of boolean field: check, checkbox, radio, select (default: select)
 *      - labels  [array]  Labels to use for 'Yes' and 'No' (default: ['NO_LABEL','YES_LABEL'])
 *  - button      Standard button
 *  - checkbox    Checkbox group
 *    - options   Array of value->label pairs to be displayed
 *  - color       HTML5 color picker    *dependant on browser support*
 *  - date        HTML5 date picker     *dependant on browser support*
 *  - datetime    HTML5 datetime picker *dependant on browser support*
 *  - dropdown    Alias for 'select'
 *  - email       HTML5 email field
 *  - file        File field
 *  - hidden      Hidden field (will be rendered just below <form> tag)
 *  - image       HTML5 image field *dependant on browser support*
 *  - month       HTML5 month picker *dependant on browser support*
 *  - multiSelect multiSelect field *requires linkedTo be defined*
 *  - number      HTML5 number field *dependant on browser support*
 *  - password    Password field (will render a confirmation field as well)
 *  - plaintext   Plaintext field with support for text-replacements *note: replacements are case sensitive*
 *  - range       HTML5 range field *dependant on browser support*
 *  - radio       Radio group
 *    - options   Array of value->label pairs to be displayed
 *  - reset       Form reset button
 *  - search      HTML5 search field
 *  - select      <select> field
 *    - options   String of options or Array of value->label pairs to be displayed
 *  - string      Alias for 'text'
 *  - submit      Form submit button
 *  - delete      Form submit button to delete the record
 *  - text        simple <input> field
 *  - textarea    Full <textarea>
 *  - tel         HTML5 tel field
 *  - time        HTML5 time picker *dependant on browser support*
 *  - url         HTML5 url field
 *  - week        HTML5 week picker *dependant on browser support*
 *  - wysiwyg     Full WYSIWYG editor
 */
class fieldBuilder{
	/** @var array An array with all default field options */
	private static $fieldDefaults = array(
		'disabled'        => FALSE,
		'disableStyling'  => FALSE,
		'duplicates'      => TRUE,
		'fieldClass'      => '',
		'fieldCSS'        => '',
		'fieldID'         => '',
		'fieldMetadata'   => array(),
		'hash'            => 'sha512',
		'help'            => array(),
		'label'           => '',
		'labelClass'      => '',
		'labelCSS'        => '',
		'labelID'         => '',
		'labelMetadata'   => array(),
		'linkedTo'        => array(),
		'multiple'        => FALSE,
		'options'         => array(),
		'placeholder'     => '',
		'primary'         => FALSE,
		'readonly'        => FALSE,
		'required'        => FALSE,
        'showIn'          => array(formBuilder::TYPE_INSERT, formBuilder::TYPE_UPDATE, formBuilder::TYPE_EDIT),
		'type'            => 'text',
		'validate'        => NULL,
		'value'           => '',
		'valueDelimiter'  => ',',
	);

	/**
	 * @var array Define default field validations based on their type (Type => Validation)
	 */
	private static $fieldValidations = array(
		'url'   => 'url',
		'email' => 'emailAddr',
		'tel'   => 'phoneNumber',
		'date'  => 'date',
	);

	/**
	 * @var formFields
	 */
	private $formFields;

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

		// Handle default field validations (based on their type)
		if(isset(self::$fieldValidations[ $this->field['type'] ]) && is_empty($this->field['validate'])){
			$this->field['validate'] = self::$fieldValidations[ $this->field['type'] ];
		}

		// If this field is required, add the 'required' class
		if($this->required) $this->labelClass .= ' required';

		// Set the default template directory container path
		$this->templateDir = __DIR__.DIRECTORY_SEPARATOR.'fieldTemplates'.DIRECTORY_SEPARATOR;
	}

	/**
	 * Return a SQL-ready string snippet for this field
	 * @return string
	 */
	public function toSqlSnippet(){
		return "`{$this->name}`=?";
	}

	/**
	 * Returns TRUE if this is a 'special' field like a button
	 * @return bool
	 */
	public function isSpecial(){
		return in_array($this->type, array('submit','delete','reset','button'));
	}

	/**
	 * Returns TRUE is this is a 'system' field that begins with '__'
	 * @return bool
	 */
	public function isSystem(){
		return (0 === strpos($this->name, '__'));
	}

	/**
	 * Returns TRUE if this is a primary field
	 * @return bool
	 */
	public function isPrimary(){
		return str2bool($this->field['primary']);
	}

	/**
	 * Returns TRUE if this field is using a link table
	 * @return bool
	 */
	public function usesLinkTable(){
		if(!sizeof($this->field['linkedTo'])) return FALSE;

		$linkedTo = $this->field['linkedTo'];
		if(!isset($linkedTo['linkTable']))        return FALSE;
		if(!isset($linkedTo['linkForeignField'])) return FALSE;
		if(!isset($linkedTo['linkLocalField']))   return FALSE;

		return TRUE;
	}

	/**
	 * [Factory] Create a fieldBuilder object. (Returns FALSE on error)
	 *
	 * @param array|string $field
	 * @param formFields   $formFields
	 * @return array|bool|fieldBuilder
	 */
	public static function createField($field, formFields $formFields=NULL){
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

		// Set a default label if it doesn't exist
		if (!isset($field['label']) || is_empty($field['label'])) {
			$field['label'] = $field['name'];
		}

		// Return a new fieldBuilder object
		$field = new self($field);
		if ($formFields instanceof formFields) $field->formFields = $formFields;
		return $field;
	}

	/**
	 * Formats the given input into a format suitable for storage in the database
	 * @param mixed $value
	 * @return mixed
	 */
	public function formatValue($value){
		if(is_array($value)) $value = implode($this->valueDelimiter, $value);
		switch($this->type){
			case 'date':
				return strtotime($value);
			case 'time':
				$time = new time;
				return $time->toSeconds($value);
			case 'password':
				return mhash($this->hash, $value);
			default:
				return $value;
		}
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
		return array('field','formFields');
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
			case 'multiselect':
				$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'multi-select.css';
				$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'multi-select.js';
		}

		// Add assets for specific field options
		$help = $this->field['help'];
		if(sizeof($help)){
			switch(@$help['type']){
				case 'modal':
					$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'modal_colorBox.min.js';
					$assets[] = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'modal_colorBox.css';
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
	 * @param string $optionName
	 * @return mixed
	 */
	private function getFieldOption($optionName){
		if (isset($this->renderOptions[$optionName])) return $this->renderOptions[$optionName];
		if (isset($this->field[$optionName])) return $this->field[$optionName];
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
		$ignoredTypes = array('hidden','button','submit','delete','reset');
		if (in_array($this->field['type'], $ignoredTypes)) return '';

        // Skip plaintext fields that haven't defined a label
        if ($this->field['type'] == 'plaintext') {
            if (!isset($options['label']) && $this->field['label'] == $this->field['name']) {
                return '';
            }
        }

		// Continue for a normal field
		$this->ensureFieldID();

		if (isset($options['name'])) unset($options['name']);
		if (isset($options['display'])) unset($options['display']);

		// Determine label
		$label = $this->getFieldOption('label');

		$this->renderOptions = $options;
		$output = isset($options['valueOnly']) && str2bool($options['valueOnly'])
			? $label
			: sprintf('<label for="%s"%s>%s</label>',
				$this->getFieldOption('fieldID'),
				$this->buildLabelAttributes(),
				$label);
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
		$fieldID = $this->getFieldOption('fieldID');
		switch(@$help['type']) {
			case 'modal':
				if(isset($help['url']) && !is_empty($help['url'])){
					// Get the source of the URL page
					return sprintf('<a href="%s" id="modalWindow_%s"><i class="icon-help"></i></a><script>$("#modalWindow_%s").colorbox({iframe:true, width:"80%%", height:"80%%"});</script>',
						$help['url'],
						$fieldID,
						$fieldID);

				}else{
					if ((!isset($help['text']) || is_empty($help['text'])) && (!isset($help['file']) || is_empty($help['file']))) {
						errorHandle::newError(__METHOD__."() Warning: No valid source provided for modal! (Please use text, url, or file)", errorHandle::DEBUG);
						return '';
					}

					if(isset($help['file'])){
						$help['text'] = file_get_contents($help['file']);
						if(get_file_mime_type($help['file']) == 'text/plain') $help['text'] = "<pre>{$help['text']}</pre>";
					}

					$contentDiv = sprintf('<div style="display: none;"><div id="modalWindowContent_%s">%s</div></div>', $fieldID, $help['text']);
					$link       = sprintf('<a href="#modalWindowContent_%s" id="modalWindow_%s"><i class="icon-help"></i></a>', $fieldID, $fieldID);
					$script     = sprintf('<script>$("#modalWindow_%s").colorbox({inline:true, width:"50%%"});</script>', $fieldID);
					return $contentDiv.$link.$script;
				}

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
		// Get the value we'll be using
		$value = $this->getFieldOption('value');

		// Catch spacial cases
		switch($this->type){
			case 'date':
				if(is_numeric($value)) $value = date('Y-m-d', $value);
				break;
			case 'time':
				$time = new time;
				if(is_numeric($value)) $value = $time->toTime($value);
				break;
		}

		return sprintf('<input type="%s" value="%s" %s%s>',
			$this->field['type'],
			str_replace('"', '&quot;', $value),
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
		$scriptTag = sprintf("<script>$('select[name^=%s]').multiSelect({
			dblClick: true
		})</script>",
			substr($this->getFieldOption('name'), 0, -2)
		);
		return $selectBox.$scriptTag;
	}

	/**
	 * [Render Helper] Render a WYSIWYG editor
	 * @return string
	 */
	private function __render_wysiwyg(){
		$wysiwygBaseURL = $this->getFieldOption('baseURL');
		if(isnull($wysiwygBaseURL)) $wysiwygBaseURL = '/wysiwyg';

		// Require our asset javascript
		$output = "<script src='$wysiwygBaseURL/ckeditor.js'></script>";

		// Make sure the textarea will have the class 'ckeditor' on it
		$fieldClass = $this->getFieldOption('class');
		if(FALSE === strpos('ckeditor', $fieldClass)) $this->renderOptions['class'] = "$fieldClass ckeditor";

		// Render the textarea w/ wrapping <div> and return the output
		$output .= sprintf('<div class="wysiwygEditor">%s</div>', $this->__render_textarea());
		return $output;
	}

	/**
	 * [Render Helper] Render a radio field
	 * @return string
	 */
	private function __render_radio(){
		$output = '';

		// Make sure there's options
		$options = $this->getFieldOption('options')
			? $this->getFieldOption('options')
			: $this->getLinkedToOptions();

		// If still no options, we don't have any and should return static message
		if(!$options) return 'No options available';

		foreach($options as $value => $label){
			// Append the value to fieldID for uniqueness
			$this->renderOptions['fieldID'] = $this->getFieldOption('fieldID')."_".str_replace(' ','_',$value);

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

		// Make sure there's options
		$options = $this->getFieldOption('options')
			? $this->getFieldOption('options')
			: $this->getLinkedToOptions();

		// If still no options, we don't have any and should return static message
		if(!$options) return 'No options available';

		// Make the given values an array (may be a CSV)
		$values = $this->getFieldOption('value');
		if(is_string($values)) $values = explode($this->getFieldOption('valueDelimiter'), $values);

		if($this->type != 'boolean'){
			// Make the name checkbox array friendly
			$this->renderOptions['name'] = $this->getFieldOption('name').'[]';

			foreach($options as $value => $label){
				// Append the value to fieldID for uniqueness
				$this->renderOptions['fieldID'] = $this->getFieldOption('fieldID')."_".str_replace(' ','_',$value);

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

		return "<div class='checkboxGroup'>$output</div>";
	}

	/**
	 * [Render Helper] Render a plaintext field
	 * @return string
	 */
	private function __render_plaintext(){
		$value      = $this->getFieldOption('value');
		$formFields = $this->formFields;
		$value      = preg_replace_callback('/\{(.*?)\}/', function ($m) use ($formFields){
			$field = $formFields->getField($m[1]);
			$value = !is_empty($field->renderedValue) ? $field->renderedValue : $field->value;
			return $value;
		}, $value);

		return $value;
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
			(!is_empty($this->getFieldOption('placeholder')) ? ' placeholder="'.$this->getFieldOption('placeholder').'"' : ''),
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
				if(isset($options['blankOption'])) $this->renderOptions['blankOption'] = $options['blankOption'];
				$this->renderOptions['options'][0] = $no;
				$this->renderOptions['options'][1] = $yes;
				return $this->__render_select();
		}
	}

	/**
	 * [Render Helper] Render a password field
	 * @return string
	 */
	private function __render_delete(){
		$type = $this->field['type'];

		$this->field['type'] = 'submit';
		$output = $this->__render_input();
		$this->field['type'] = $type;

		return $output;
	}

	/**
	 * Build the attribute pairs for the label element
	 *
	 * @return string
	 */
	private function buildLabelAttributes(){
		$attributes = (array)$this->getFieldOption('labelMetadata');

		if (!$this->getFieldOption('disableStyling')) {
			if (!is_empty($this->getFieldOption('labelCSS'))) $attributes['style'] = $this->getFieldOption('labelCSS');
			if (!is_empty($this->getFieldOption('labelClass'))) $attributes['class'] = $this->getFieldOption('labelClass');
		}

		return $this->__buildAttributes($attributes);
	}

	/**
	 * Build the attribute pairs for the field element
	 *
	 * @return string
	 */
	private function buildFieldAttributes(){
		$attributes         = (array)$this->getFieldOption('fieldMetadata');
		$attributes['name'] = $this->getFieldOption('name');

		if (str2bool($this->getFieldOption('disabled'))) $attributes['bool'][] = 'disabled';
		if (str2bool($this->getFieldOption('readonly'))) $attributes['bool'][] = 'readonly';
		if (str2bool($this->getFieldOption('required'))) $attributes['bool'][] = 'required';
		if (str2bool($this->getFieldOption('multiple'))) $attributes['bool'][] = 'multiple';

		if ($fieldID = $this->getFieldOption('fieldID')) $attributes['id'] = $fieldID;
		if (!$this->getFieldOption('disableStyling')) {
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

		$options = $this->getFieldOption('options')
			? $this->getFieldOption('options')
			: $this->getLinkedToOptions();

		// If still no options, we don't have any and should return an empty string
		if(!$options){
			errorHandle::newError(__METHOD__.'() No options provided for select field!', errorHandle::DEBUG);
			return '';
		}

        // If the options were provided as a string instead of an array, just return the string
        if (is_string($options)) return $options;

		// Prepend a 'blank option'?
		if($blankOption = $this->getFieldOption('blankOption')){
			$blankOption = ($blankOption !== TRUE) ? $blankOption : '';
			$output .= sprintf('<option value="NULL">%s</option>', $blankOption);
		}

		// Loop, and build the options
		foreach ($options as $key => $label) {
			$selected = in_array($key, (array)$this->getFieldOption('value'))
				? ' selected'
				: '';
			$output .= sprintf('<option value="%s"%s>%s</option>',
				$key,
				$selected,
				htmlSanitize($label));
		}
		return $output;

	}

	/**
	 * Returns an options array based on the linkedTo settings
	 *
	 * @return array|bool The options array or FALSE in case of error
	 */
	private function getLinkedToOptions(){
		// Get the linkedTo settings
		$linkedTo = $this->getFieldOption('linkedTo');

		// If no linkedTo settings, return FALSE
		if(is_empty($linkedTo)) return FALSE;

		// Pull out all the settings we need
		$dbConnection     = isset($linkedTo['dbConnection'])     ? $linkedTo['dbConnection']     : 'appDB';
		$foreignTable     = isset($linkedTo['foreignTable'])     ? $linkedTo['foreignTable']     : NULL;
		$foreignKey       = isset($linkedTo['foreignKey'])       ? $linkedTo['foreignKey']       : 'ID';
		$foreignLabel     = isset($linkedTo['foreignLabel'])     ? $linkedTo['foreignLabel']     : NULL;
		$foreignOrder     = isset($linkedTo['foreignOrder'])     ? $linkedTo['foreignOrder']     : NULL;
		$foreignWhere     = isset($linkedTo['foreignWhere'])     ? $linkedTo['foreignWhere']     : NULL;
		$foreignLimit     = isset($linkedTo['foreignLimit'])     ? $linkedTo['foreignLimit']     : NULL;
		$foreignSQL       = isset($linkedTo['foreignSQL'])       ? $linkedTo['foreignSQL']       : NULL;
		$linkTable        = isset($linkedTo['linkTable'])        ? $linkedTo['linkTable']        : NULL;
		$linkLocalField   = isset($linkedTo['linkLocalField'])   ? $linkedTo['linkLocalField']   : NULL;
		$linkForeignField = isset($linkedTo['linkForeignField']) ? $linkedTo['linkForeignField'] : NULL;

		// Get the db connection we'll be talking to
		$db = db::getInstance()->$dbConnection;

		// Build the SQL (if needed)
		if (!isset($foreignSQL)) {
			if (!isset($foreignKey) || !isset($foreignLabel) || !isset($foreignTable)) {
				errorHandle::newError(__METHOD__."() Using linkedTo but missing key, field, and/or table params", errorHandle::DEBUG);
				return FALSE;
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
		if($sqlResult->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error: {$sqlResult->errorCode()}:{$sqlResult->errorMsg()} (SQL: $foreignSQL)", errorHandle::DEBUG);
			return FALSE;
		}

		// Format the result into a usable array
		$options = array();
		while ($row = $sqlResult->fetch()) {
			$key           = array_shift($row); // The key is always the 1st col
			$label         = array_shift($row); // The key is always the 2nd col
			$options[$key] = $label;
		}


		// If this is a linkTable field AND there are no currently set values (like from POST) then we need to set them
		if(!isset($this->renderOptions['value']) && $linkTable){
			// Get the primary field
			$primaryField = array_shift($this->formFields->getPrimaryFields()); // Shift 1st item off the array, ensures we get the 1st defined primary field
			$primaryValue = $primaryField->value;

			// Grab the values from the link table
			$sql = sprintf('SELECT `%s` FROM `%s` WHERE `%s`=?',
				$db->escape($linkForeignField),
				$db->escape($linkTable),
				$db->escape($linkLocalField));
			$sqlResult = $db->query($sql, array($primaryValue));
			if($sqlResult->errorCode()){
				errorHandle::newError(__METHOD__."() SQL Error: {$sqlResult->errorCode()}:{$sqlResult->errorMsg()} (SQL: $sql)", errorHandle::DEBUG);
				return FALSE;
			}

			// Get the list of foreign values which is the values for this field's options
			$this->renderOptions['value'] = $sqlResult->fetchFieldAll();
		}

		return $options;
	}

}
