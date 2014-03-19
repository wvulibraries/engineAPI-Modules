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

		// Figure out the fields we need to loop on (and save the number of fields)
		preg_match_all('/{field.*?name="(\w+)".*?}/', $block, $matches);
		$templateFields      = array_intersect($sqlResult->fieldNames(), $matches[1]);
		$this->counterFields = sizeof($templateFields);

		// Loop over each row, transforming the block into a compiled block
		while ($row = $sqlResult->fetch()) {
			$rowBlock = $block;

			// Perform global replacements
			$rowID    = uniqid();
			$rowBlock = str_replace('{rowLoopID}', $rowID, $rowBlock);
			$rowData = array();
			foreach($this->formBuilder->listPrimaryFields() as $field){
				$rowData[$field] = $row[$field];
			}
			$this->formBuilder->editTableRowData[$rowID] = $rowData;

			// Perform field replacements
			foreach ($row as $field => $value) {
				if (!in_array($field, $templateFields)) continue;
				$rowBlock = preg_replace('/{field\s+((?=[^{]*name="'.preg_quote($field).'".*)(?![^{]*value=".+".*).*?)}/', '{field $1 value="'.$value.'" rowID="'.$rowID.'"}', $rowBlock, -1, $count);
			}

			$controls = '';
			if(isset($this->renderOptions['expandable']) && $this->renderOptions['expandable']){
				$controls .= '<i class="icon icon-collapse" data-target="#updateForm_'.$rowID.'" title="Expand"></i>';
			}
			$controls .= '<i class="icon icon-trash" data-target="#updateForm_'.$rowID.'" title="Delete"></i>';
			$rowBlock = str_replace('{controls}', $controls, $rowBlock);

			$output .= $rowBlock;
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
	 * [PREG Callback] Process general template tags
	 *
	 * @param array $matches
	 * @return string
	 */
	private function __renderGeneralTags($matches){
		$tmplTag   = trim($matches[1]);
		$attrPairs = attPairs($matches[2]);
		switch (strtolower($tmplTag)) {
			case 'formtitle':
				return $this->formBuilder->formLabel;

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

				if(isset($attrPairs['rowID'])) {
					$attrPairs['name'] .= "[{$attrPairs['rowID']}]";
					unset($attrPairs['rowID']);
				}

				$display  = isset($attrPairs['display'])
					? trim(strtolower($attrPairs['display']))
					: 'full';
				$template = isset($attrPairs['template'])
					? trim(strtolower($attrPairs['template']))
					: NULL;

				switch ($display) {
					case 'full':
						return $field->render($template, $attrPairs);
					case 'field':
						return $field->renderField($attrPairs);
					case 'label':
						return $field->renderLabel($attrPairs);
					default:
						errorHandle::newError(__METHOD__."() Invalid 'display' for field '{$attrPairs['name']}'! (only full|field|label valid)", errorHandle::DEBUG);
						return '';
				}

			case 'controlslabel':
				return isset($this->renderOptions['controlsLabel'])
					? $this->renderOptions['controlsLabel']
					: '';

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