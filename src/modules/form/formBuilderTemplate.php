<?php
class formBuilderTemplate {
	/**
	 * @var formBuilder
	 */
	private $formBuilder;

	/**
	 * @var array Render options set from formBuilder
	 */
	public $renderOptions=array();

	/**
	 * @var string The template text to use when rendering
	 */
	private $template;

	/**
	 * Array of 'data-' attributes to be included on the <form> tag at render time
	 * @var array
	 */
	public $formDataAttributes = array();

	/**
	 * Array of attributes to be included on the <form> tag at render time
	 * @var array
	 */
	public $formAttributes = array();

	/**
	 * @var string The generated formID for this form
	 */
	public $formID;

	/**
	 * @var string[] List of fields which have been rendered (used for de-duping during render)
	 */
	private $fieldsRendered = array();

	/**
	 * @var int Counts the number of rows
	 */
	private $counterRows = 0;

	/**
	 * @var int Counts the number of fields
	 */
	private $counterFields = 0;

	/**
	 * Class constructor
	 * @param formBuilder $formBuilder
	 * @param string $templateText
	 */
	function __construct($formBuilder, $templateText=''){
		$this->formBuilder = $formBuilder;
		$this->template    = $templateText;
	}

	/**
	 * Render the form template
	 * @param string $templateText
	 * @return string
	 */
	public function render($templateText=NULL){
		// Reset the list of rendered fields
		$this->fieldsRendered = array();

		// Set all primary fields as disabled for security
		foreach($this->formBuilder->listPrimaryFields() as $primaryField){
			$this->formBuilder->modifyField($primaryField,'disabled',TRUE);
		}

		// Make a local copy of the template's source to work with
		if(isnull($templateText)) $templateText = $this->template;

		// Process {ifFormErrors} and {formErrors}
		$patterns = array('|{ifFormErrors}(.+?){/ifFormErrors}|ism', '|{formErrors}|i');
		$templateText = preg_replace_callback($patterns, array($this, '__renderFormErrors'), $templateText);

		// Process {ifExpandable}
		if(isset($this->renderOptions['expandable']) && $this->renderOptions['expandable']){
			// Expandable enabled: only remove the {ifExpandable} and {/ifExpandable} tags
			$templateText = preg_replace('|{/?ifExpandable}|i', '', $templateText);
		}else{
			// Expandable disabled: remove entire {ifExpandable} block
			$templateText = preg_replace('|{ifExpandable(.*?)}(.+?){/ifExpandable}|ism', '', $templateText);
		}

		// Process {fieldsLoop}
		$templateText = preg_replace_callback('|{fieldsLoop(.*?)}(.+?){/fieldsLoop}|ism', array($this, '__renderFieldLoop'), $templateText);

		// Process {rowLoop}
		$templateText = preg_replace_callback('|{rowLoop(.*?)}(.+?){/rowLoop}|ism', array($this, '__renderRowLoop'), $templateText);

		// Process general tags
		$templateText = preg_replace_callback('|{([/\w]+)\s?(.*?)}|ism', array($this, '__renderGeneralTags'), $templateText);

		return $templateText;
	}

	/**
	 * [PREG Callback] Process all {fieldLoop}'s
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __renderFieldLoop($matches){
		$output     = '';
		$options    = attPairs($matches[1]);
		$block      = trim($matches[2]);
		$list       = isset($options['list'])       ? explode(',', $options['list'])   : $this->formBuilder->listFields();
		$editStrip  = isset($options['editStrip'])  ? str2bool($options['editStrip'])  : NULL;
		$showHidden = isset($options['showHidden']) ? str2bool($options['showHidden']) : TRUE;

		if ($showHidden || $showHidden === NULL) {
			foreach ($this->formBuilder->getFields() as $field) {
				// Skip the field if it's not in the list
				if (!in_array($field->name, $list)) continue;
				// Skip fields that have already been rendered
				if (in_array($field->name, $this->fieldsRendered)) continue;
				// We only care if this is a hidden field
				if ($field->type == 'hidden') {
					$output .= $field->render();
					$this->fieldsRendered[] = $field->name;
				}
			}
		}

		foreach ($this->formBuilder->getSortedFields($editStrip) as $field) {
			// Skip fields that have already been rendered
			if (in_array($field->name, $this->fieldsRendered)) continue;

			// Skip any hidden fields, we've already processed them
			if ($field->type == 'hidden') continue;

			// Skip the field if it's not in the list
			if (!in_array($field->name, $list)) continue;

			// Replace any unnamed field with a named version for this field
			$output .= preg_replace('/{field(?!.*name=".+".*)(.*)}/', '{field $1 name="'.$field->name.'"}', $block);
		}

		return $output;
	}

	/**
	 * [PREG Callback] Process all {rowLoop}'s
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __renderRowLoop($matches){
		$output  = '';
		$options = attPairs($matches[1]);
		$block   = trim($matches[2]);

		// Extract db table stuff into vars
		$dbOptions    = $this->formBuilder->dbOptions;
		$dbConnection = isset($dbOptions['connection']) ? $dbOptions['connection'] : 'appDB';
		$order        = isset($dbOptions['order'])      ? $dbOptions['order']      : NULL;
		$where        = isset($dbOptions['where'])      ? $dbOptions['where']      : NULL;
		$limit        = isset($dbOptions['limit'])      ? $dbOptions['limit']      : NULL;
		$table        = $dbOptions['table'];

		// Sanity check
		if (isnull($table)) {
			errorHandle::newError(__METHOD__."() No table defined in dbTableOptions! (Did you forget to call linkToDatabase()?)", errorHandle::DEBUG);
			return '';
		}

		// Get the db connection we'll be talking to
		if (!$db = db::get($dbConnection)) {
			errorHandle::newError(__METHOD__."() Database connection failed to establish", errorHandle::DEBUG);
			return '';
		}

		// Build the SQL
		$sql = sprintf('SELECT * FROM `%s`', $db->escape($table));
		if (!is_empty($where)) $sql .= " WHERE $where";
		if (!is_empty($order)) $sql .= " ORDER BY $order";
		if (!is_empty($limit)) $sql .= " LIMIT $limit";

		// Run the SQL
		$sqlResult = $db->query($sql);

		// Catch any sql error
		if($sqlResult->errorCode()){
			errorHandle::newError(__METHOD__."() SQL Error: {$sqlResult->errorCode()}:{$sqlResult->errorMsg()}", errorHandle::HIGH);
			die('Internal database error!');
		}

		// Save the number of rows
		$this->counterRows = $sqlResult->rowCount();
		if($this->counterRows){
			while($dbRow = $sqlResult->fetch()){
				$rowBlock = $block;
				$rowID    = uniqid();
				$deferedFields = array();

				// Global replacements
				$rowBlock = str_replace('{rowLoopID}', $rowID, $rowBlock);

				// Save this row's primary fields for later (like during processing)
				$rowData = array();
				foreach($this->formBuilder->listPrimaryFields() as $field){
					$rowData[$field] = $dbRow[$field];
				}
				$this->formBuilder->editTableRowData[$rowID] = $rowData;


				// Regex grabbing all fields
				preg_match_all('/{field.*?name="(\w+)".*?}/', $rowBlock, $matches);
				foreach($matches[1] as $matchID => $fieldName){
					$fieldTag = $matches[0][$matchID];
					$field = $this->formBuilder->getField($fieldName);

					// Append the rowID onto the field's name
					list($fieldTag, $rowBlock) = str_replace('name="'.$fieldName.'"', 'name="'.$fieldName.'['.$rowID.']"', array($fieldTag, $rowBlock));

					// If this is a plaintext field, defer it till later
					if($field->type == 'plaintext'){
						$deferedFields[] = array('fieldTag' => $fieldTag, 'field' => $field);
						continue;
					}

					// Render the field tag!
					$renderedField = $this->__renderFieldTag($fieldTag, $field, $dbRow[$fieldName], $fieldTag);

					// Replace the field tag with it's fully rendered version
					$rowBlock = str_replace($fieldTag, $renderedField, $rowBlock);
				}

				// Now process any deferred fields
				foreach($deferedFields as $deferedField){
					$fieldTag      = $deferedField['fieldTag'];
					$field         = $deferedField['field'];
					$renderedField = $this->__renderFieldTag($fieldTag, $field, NULL, $fieldTag);
					$rowBlock         = str_replace($fieldTag, $renderedField, $rowBlock);
				}

				$output .= $rowBlock;
			}
		}

		// Replace any field or row count tags inside our block
		$output = str_replace('{rowCount}', $this->counterRows, $output);
		$output = str_replace('{fieldCount}', $this->counterFields, $output);

		// Return the compiled block
		return $output;
	}

	/**
	 * [PREG Callback] Process all {ifFormErrors} and {formErrors}
	 * @param $matches
	 * @return string
	 */
	private function __renderFormErrors($matches){
		$block = $matches[0];

		// Build formErrors HTML and if there's none, return an empty string
		$formErrorHTML = errorHandle::prettyPrint();
		if(!$formErrorHTML) return '';

		// If there's a block move into it, and replace {formErrors} with the formErrorsHTML from above
		if(sizeof($matches) > 1){
			$block = $matches[1];
			return str_replace('{formErrors}', $formErrorHTML, $block);
		}else{
			return $formErrorHTML;
		}
	}

	/**
	 * Fully render a given field
	 *
	 * @param              $tag
	 * @param fieldBuilder $field
	 * @param string       $value
	 * @param string       $errorReturn
	 * @return string
	 */
	function __renderFieldTag($tag, fieldBuilder $field, $value=NULL, $errorReturn=''){
		// Get the attribute pairs for this field tag
		preg_match('/^{\w+(.+)}$/', $tag, $matches);
		$attrPairs = attPairs($matches[1]);

		if(isset($value)) $attrPairs['value'] = $value;

		$display  = isset($attrPairs['display'])
			? trim(strtolower($attrPairs['display']))
			: 'full';
		$template = isset($attrPairs['template'])
			? trim(strtolower($attrPairs['template']))
			: NULL;

		// Render the field tag
		switch ($display) {
			case 'full':
				return $field->render($template, $attrPairs);
				break;
			case 'field':
				return $field->renderField($attrPairs);
				break;
			case 'label':
				return $field->renderLabel($attrPairs);
				break;
			default:
				errorHandle::newError(__METHOD__."() Invalid 'display' for field '{$attrPairs['name']}'! (only full|field|label valid)", errorHandle::DEBUG);
		}

		return $errorReturn;
	}

	/**
	 * [PREG Callback] Process general template tags
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __renderGeneralTags($matches){
		$tmplTag   = $matches[0];
		$tagName   = trim($matches[1]);
		$attrPairs = attPairs($matches[2]);
		switch (strtolower($tagName)) {
			case 'formtitle':
				return $this->renderOptions['title'];

			case 'form':
				$output = '';
				$showHidden = isset($attrPairs['hidden']) ? str2bool($attrPairs['hidden']) : FALSE;
				unset($attrPairs['hidden']);

				// Build the <form> tag
				if(!isset($this->renderOptions['noFormTag']) || !$this->renderOptions['noFormTag']){
					// Compile form attributes
					$attrs = array();
					foreach(array_merge($this->formAttributes, $attrPairs) as $attr => $value){
						$attrs[] = $attr.'="'.addslashes($value).'"';
					}

					// Compile form data attributes
					foreach($this->formDataAttributes as $attr => $value){
						$attrs[] = 'data-'.$attr.'="'.addslashes($value).'"';
					}

					$output .= sprintf('<form method="post"%s>', (sizeof($attrs) ? ' '.implode(' ', $attrs): ''));
				}

				// Include the formName
				$output .= sprintf('<input type="hidden" name="__formID" value="%s">', $this->formID);

				// Include the CSRF token
				list($csrfID, $csrfToken) = session::csrfTokenRequest();
				$output .= sprintf('<input type="hidden" name="__csrfID" value="%s">', $csrfID);
				$output .= sprintf('<input type="hidden" name="__csrfToken" value="%s">', $csrfToken);

				// Add any hidden fields (if needed)
				if($showHidden){
					foreach($this->formBuilder->getFields() as $field){
						if (in_array($field->name, $this->fieldsRendered)) continue;
						if($field->type == 'hidden') {
							$output .= $field->render();
							$this->fieldsRendered[] = $field->name;
						}
					}
				}

				// Return the result
				return $output;

			case '/form':
				return (isset($this->renderOptions['noFormTag']) && $this->renderOptions['noFormTag'])
					? ''
					: '</form>';

			case 'fields':
				$output  = '';
				$display = isset($attrPairs['display'])
					? trim(strtolower($attrPairs['display']))
					: 'full';

				foreach ($this->formBuilder->getFields() as $field) {
					if (in_array($field->name, $this->fieldsRendered)) continue;

					switch ($display) {
						case 'full':
							$this->fieldsRendered[] = $field->name;
							$output .= $field->render();
							break;
						case 'fields':
							$this->fieldsRendered[] = $field->name;
							$output .= $field->renderField();
							break;
						case 'labels':
							$this->fieldsRendered[] = $field->name;
							$output .= $field->renderLabel();
							break;
						case 'hidden':
							if($field->type == 'hidden'){
								$this->fieldsRendered[] = $field->name;
								$output .= $field->render();
							}
							break;
						default:
							errorHandle::newError(__METHOD__."() Invalid 'display' for {fields}! (only full|fields|labels|hidden valid)", errorHandle::DEBUG);
							return '';
					}
				}

				return $output;

			case 'field':
				if (!isset($attrPairs['name'])) {
					errorHandle::newError(__METHOD__."() 'name' is required for {field} tags", errorHandle::DEBUG);
					return '';
				}

				$field = $this->formBuilder->getField($attrPairs['name']);
				if (isnull($field)) {
					errorHandle::newError(__METHOD__."() No field defined for '{$attrPairs['name']}'!", errorHandle::DEBUG);
					return '';
				}

				return $this->__renderFieldTag($tmplTag, $field);

			case 'fieldset':
				$legend = isset($attrPairs['legend']) && !is_empty($attrPairs['legend'])
					? '<legend>'.$attrPairs['legend'].'</legend>'
					: '';

				return '<fieldset>'.$legend;

			case '/fieldset':
				return '</fieldset>';

			case 'rowcount':
				return (string)$this->counterRows;

			case 'fieldcount':
				return (string)$this->counterFields;

			// By default we need to return the whole tag because it must not be one of our tags.
			default:
				return $matches[0];
		}
	}
}