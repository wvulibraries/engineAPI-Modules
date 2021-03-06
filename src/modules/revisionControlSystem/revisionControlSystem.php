<?php
/**
 * EngineAPI revisionControlSystem
 * @package EngineAPI\modules\revisionControlSystem
 */

require("simplediff.php");

/**
 * EngineAPI revisionControlSystem module
 * @package EngineAPI\modules\revisionControlSystem
 */
class revisionControlSystem {

	public  $digitalObjectsFieldName = "digitalObjects";

	// Variables to configure the Revision Table
	/**
	 * Display the revert buttons (otherwise its a "view only" table)
	 * @var bool
	 */
	public $displayRevert = TRUE;
	/**
	 * display the radio buttons for comparing items
	 * @var bool
	 */
	public  $displayCompare = TRUE;

	private $productionTable = NULL;
	private $revisionTable   = NULL;
	/**
	 * Key in production table
	 * @var string
	 */
	private $primaryID = NULL;
	/**
	 * secondary key in revision table, will usually be a modified date
	 * Note: secondary key must exist in both the primary and secondary tables
	 * @var string
	 */
	private $secondaryID = NULL;

	/**
	 * @var dbDriver
	 */
	private $db              = NULL;
	private $excludeFields   = array();
	private $relatedMappings = array();

	/**
	 * Class constructor
	 *
	 * @param $productionTable
	 *        Table where production data is being stored
	 * @param $revisionTable
	 *        Table where revision information is being stored
	 * @param $primaryID
	 *        Field name of the primary key in the production table
	 * @param $secondaryID
	 *        Field name of the secondary key in the revision table
	 * @param dbDriver $database
	 *        Database object to use
	 */
	function __construct($productionTable,$revisionTable,$primaryID,$secondaryID,$database=NULL) {
		$this->set_database($database);

		$this->revisionTable   = $revisionTable;
		$this->productionTable = $productionTable;
		$this->primaryID 	   = $primaryID;
		$this->secondaryID 	   = $secondaryID;

	}

	function __destruct() {

	}

	/**
	 * Sets the internal database connection
	 * @param dbDriver|string $database
	 */
	public function set_database($database='appDB'){
		$this->db = $database instanceof dbDriver
			? $database
			: db::get($database);
	}

	/**
	 * Add a new revision to the database
	 *
	 * @param $primaryIDValue
	 *        The primary ID of the item that we will be inserting into the revision table
	 * @return bool
	 */
	public function insertRevision($primaryIDValue) {

		/* ** Begin Insert Check ** */

		// check to see if the current version of the item is already in the revision table
		// Determined by checking the modified date. It is assumed that the developer is checking,
		// on the application level, if the data should have been inserted or not (that is, if the
		// update button was clicked abut nothing was changed, the modified date wasn't updated)

		// Get the value of the secondary key
		$sql       = sprintf("SELECT %s FROM %s WHERE %s=?",
			$this->db->escape($this->secondaryID),
			$this->db->escape($this->productionTable),
			$this->db->escape($this->primaryID)
			);
		$sqlResult = $this->db->query($sql, array($primaryIDValue));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error determing original secondary key value", errorHandle::DEBUG);
			return(FALSE);
		}

		$row              = $sqlResult->fetch();
		$secondaryIDValue = $row[$this->secondaryID];

		// Check to see if the secondary / primary key pair exists in the revision table already
		$sql = sprintf("SELECT COUNT(*) FROM %s WHERE `primaryID`=? AND `secondaryID`=?",
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($primaryIDValue, $primaryIDValue));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error determing revision duplication. ".$sqlResult->errorMsg(), errorHandle::DEBUG);
			return(FALSE);
		}

		// they key already exists, so we return TRUE because nothing needs done
		if ($sqlResult->fetchField()) return TRUE;

		/* ** End Insert Check ** */

		// Get all data from the primary table
		// Selecting on secondary ID as well, to be sure that it hasn't been updated since requesting revision control
		$sql = sprintf("SELECT * FROM %s WHERE %s=? AND %s=?",
			$this->db->escape($this->productionTable),
			$this->db->escape($this->primaryID),
			$this->db->escape($this->secondaryID)
			);

		$sqlResult = $this->db->query($sql, array($primaryIDValue, $secondaryIDValue));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() -Error getting data from primary table, sql Error = ".$sqlResult->errorMsg(), errorHandle::CRITICAL);
			return(FALSE);
		}

		$row = $sqlResult->fetch();

		// We don't need the primary and secondary fields in the array
		unset($row[$this->primaryID]);
		unset($row[$this->secondaryID]);

		if (isset($row[$this->digitalObjectsFieldName])) {

			$digitalObjectArray = $row[$this->digitalObjectsFieldName];
			unset($row[$this->digitalObjectsFieldName]);

		}
		else {
			$digitalObjectArray = "";
		}

		// If there are any fields that the developer has specifically 
		// excluded from being managed in revision control, we skip them here. 
		foreach ($this->excludeFields as $I=>$V) {
			unset($row[$V]);
		}

		$metaDataArray = base64_encode(serialize($row));

		$relatedDataArray = array();
		if (count($this->relatedMappings) > 0) {
			// Get the related data from other tables
			foreach ($this->relatedMappings as $I=>$V) {
				$sql       = sprintf("SELECT * FROM %s WHERE `%s`=?",
					$this->db->escape($V['table']),
					$this->db->escape($V['primaryKey'])
					);
				$sqlResult = $this->db->query($sql, array($primaryIDValue));

				if ($sqlResult->errorCode()) {
					errorHandle::newError(__METHOD__."() - error getting related data.", errorHandle::DEBUG);
					return(FALSE);
				}

				$relatedDataArray[$V['table']] = $sqlResult->fetchAll();
			}
			$relatedDataArray = base64_encode(serialize($relatedDataArray));
		}
		else {
			$relatedDataArray = "";
		}

		// find duplicates
		// The Revision Control system will automatically find duplicate data in each of the 3 arrays
		// and link to the last occurrence of the data. 
		// Note: We don't care where the data comes from, if its the same its the same. 

		// Find for metaDataArray
		$sql       = sprintf("SELECT ID FROM %s WHERE metaData=? LIMIT 1",
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($metaDataArray));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error finding duplicates for Metadata Array", errorHandle::DEBUG);
			return(FALSE);
		}

		if ($sqlResult->rowCount()) $metaDataArray = $sqlResult->fetchField();

		// Find for digitalObjectArray
		if (!is_empty($digitalObjectArray)) {
			$sql       = sprintf("SELECT ID FROM %s WHERE digitalObjects=? LIMIT 1",
				$this->db->escape($this->revisionTable)
				);
			$sqlResult = $this->db->query($sql, array($digitalObjectArray));

			if ($sqlResult->errorCode()) {
				errorHandle::newError(__METHOD__."() - Error finding duplicates for Data Object Array", errorHandle::DEBUG);
				return(FALSE);
			}

			if ($sqlResult->rowCount()) $digitalObjectArray = $sqlResult->fetchField();
		}

		// Find for relatedDataArray
		if (!is_empty($relatedDataArray)) {
			$sql       = sprintf("SELECT ID FROM %s WHERE relatedData=? LIMIT 1",
				$this->db->escape($this->revisionTable)
				);
			$sqlResult = $this->db->query($sql, array($relatedDataArray));

			if ($sqlResult->errorCode()) {
				errorHandle::newError(__METHOD__."() - Error finding duplicates for Related Data Array", errorHandle::DEBUG);
				return(FALSE);
			}

			if ($sqlResult->rowCount()) $relatedDataArray = $sqlResult->fetchField();
		}

		$sql = sprintf("INSERT INTO %s (productionTable,primaryID,secondaryID,metaData,digitalObjects,relatedData) VALUES(?,?,?,?,?,?)",
			$this->db->escape($this->revisionTable)
			);

		$data      = array($this->productionTable, $primaryIDValue, $secondaryIDValue, $metaDataArray, $digitalObjectArray, $relatedDataArray);
		$sqlResult = $this->db->query($sql, $data);

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error Inserting revision into revision table. ".$sqlResult->errorMsg(), errorHandle::DEBUG);
			return(FALSE);
		}

		return(TRUE);
	}

	/**
	 * Revert to a past revision
	 *
	 * @param $primaryIDValue
	 * @param $secondaryIDValue
	 * @return bool
	 */
	public function revert2Revision($primaryIDValue,$secondaryIDValue) {

		//Begin database Transactions
		$result = $this->db->beginTransaction();
		if ($result !== TRUE) {
			errorHandle::errorMsg("Database transactions could not begin.");
			errorHandle::newError(__METHOD__."() - unable to start database transactions", errorHandle::DEBUG);
			return(FALSE);
		}

		// Move the current production value into the modified table
		$prod2RevResult = $this->insertRevision($primaryIDValue,$secondaryIDValue);

		if ($prod2RevResult === FALSE) {
			errorHandle::newError("Error Copying row from production to revision tables", errorHandle::DEBUG);
			errorHandle::errorMsg("Error reverting to previous revision.");

			// roll back database transaction
			$this->db->rollback();

			return(FALSE);
		}

		$sql       = sprintf("SELECT * FROM `%s` WHERE `productionTable`=? AND `primaryID`=? AND `secondaryID`=?",
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($this->productionTable, $primaryIDValue, $secondaryIDValue));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error retrieving revision from table.", errorHandle::DEBUG);

			// roll back database transaction
			$this->db->rollback();

			return(FALSE);
		}
		else if ($sqlResult->rowCount() < 1) {
			errorHandle::newError(__METHOD__."() - Requested Revision not found in system", errorHandle::DEBUG);

			// roll back database transaction
			$this->db->rollback();

			return(FALSE);
		}

		$row = $sqlResult->fetch();

		// $row['metadata']        = unserialize(base64_decode($row['metadata']));
		$row['metadata']    = $this->getMetadataForID($row['ID']);
		$row['relatedData'] = $this->getMetadataForID($row['ID'],"relatedData");

		// Retrieve digital object if it is a link
		if (validate::integer($row['digitalObjects'])) {
			$sql       = sprintf("SELECT `digitalObjects` FROM %s WHERE `ID`=?",
				$this->db->escape($this->revisionTable)
				);

			$sqlResult = $this->db->query($sql, array($row['digitalObjects']));

			if ($sqlResult->errorCode()) {
				errorHandle::newError(__METHOD__."() - ", errorHandle::DEBUG);

				// roll back database transaction
				$this->db->rollback();

				return(FALSE);
			}

			$row2                  = $sqlResult->fetch();
			$row['digitalObjects'] = $row2['digitalObjects'];

		}

		$setString = array();
		foreach ($row['metadata'] as $I=>$V) {
			$setString[] = sprintf("%s='%s'",
				$this->db->escape($I),
				$this->db->escape($V)
				);
		}
		$setString = implode(",",$setString);

		if (!is_empty($row['digitalObjects'])) {
			$setString .= sprintf(",`digitalObjects`='%s'",
				$this->db->escape($row['digitalObjects'])
				);
		}

		// Add the primary and secondary fields back in
		$setString .= sprintf(",`%s`='%s',`%s`='%s'",
			$this->db->escape($this->primaryID),
			$this->db->escape($primaryIDValue),
			$this->db->escape($this->secondaryID),
			$this->db->escape($secondaryIDValue)
			);

		// Restore into production table
		$sql       = sprintf("REPLACE INTO %s SET %s",
			$this->db->escape($this->productionTable),
			$setString
			);
		$sqlResult = $this->db->query($sql);

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Restoring.". $sqlResult->errorMsg()."SQL: ".$sql, errorHandle::DEBUG);

			// roll back database transaction
			$this->db->rollback();

			return(FALSE);
		}

		// Restore Related Data
		foreach ($row['relatedData'] as $table=>$rows) {

			// Delete the current set of data in the related table
			$sql       = sprintf("DELETE FROM %s WHERE `%s`=?",
				$this->db->escape($table),
				$this->db->escape($this->relatedMappings[$table]['primaryKey'])
				);
			$sqlResult = $this->db->query($sql, array($primaryIDValue));

			if ($sqlResult->errorCode()) {
				errorHandle::newError(__METHOD__."() - Error deleting from related data table, ".$table.", with error: ".$sqlResult->errorMsg(), errorHandle::DEBUG);

				// roll back database transaction
				$this->db->rollback();

				return(FALSE);
			}

			foreach ($rows as $I=>$row) {

				$temp = array();

				foreach ($row as $field=>$value) {
					$temp[] = sprintf("`%s`='%s'",
						$this->db->escape($field),
						$this->db->escape($value)
						);
				}

				$temp = implode(",",$temp);

				$sql       = sprintf("INSERT INTO `%s` SET %s",
					$this->db->escape($table),
					$temp
					);
				$sqlResult = $this->db->query($sql);

				if ($sqlResult->errorCode()) {
					errorHandle::newError(__METHOD__."() - Restoring related Data to table, ".$table.", with error: ".$sqlResult->errorMsg(), errorHandle::DEBUG);
					return(FALSE);
				}

			}

		}

		// commit database transactions
		$this->db->commit();

		errorHandle::successMsg("Successfully reverted to revision.");

		return(TRUE);

	}

	/**
	 * Generate HTML revision table
	 *
     * ###Display Fields:
     * - field: Field is the field name in the actual table.
     * - label: the heading for that field in the display table.
     * - translation: if present it must be either and array or a function.
     *   - if an array, each index of the array must corrispond do a potential value.
     *   - if a function that function must take an argument, which is the value of the field.
     *
	 * @param $primaryIDValue
	 *        Value of the $primaryIDField. NOT SANITIZED, Expects clean value.
	 * @param $displayFields
	 *        An array that contains information about each field to be displayed in the revision table.
	 * @return bool|string
	 */
	public function generateRevisionTable($primaryIDValue,$displayFields) {

		$sql = sprintf("SELECT * FROM `%s` WHERE `productionTable`=? AND `primaryID`=?",
			$this->db->escape($this->revisionTable)
			);

		$sqlResult = $this->db->query($sql, array($this->productionTable, $primaryIDValue));

		if ($sqlResult->errorCode()) {
			errorHandle::newError("Error retrieving revision information. sql: ".$sql." SQL ERROR: ".$sqlResult->errorMsg(), errorHandle::DEBUG);
			errorHandle::errorMsg("Error retrieving revision information.");
			return(FALSE);
		}

		if (!$sqlResult->rowCount()) {
			$error = TRUE;
			errorHandle::errorMsg("No Revisions found for this item.");
		}

		$revArray     = array();
		$tableHeaders = array();
		$firstItem    = TRUE;

		if ($this->displayRevert === TRUE) {
			$tableHeaders[] = "Revert";
		}

		if ($this->displayCompare === TRUE) {
			$tableHeaders[] = "Compare 1";
			$tableHeaders[] = "Compare 2";
		}

		while ($row = $sqlResult->fetch()) {

			$metadata = $this->getMetadataForID($row['ID']);

			$temp     = array();

			if ($this->displayRevert === TRUE) {
				$temp["Revert"]    = '<input type="radio" name="revert"   value='.$row['secondaryID'].' />';
			}

			if ($this->displayCompare === TRUE) {
				$temp["Compare 1"] = '<input type="radio" name="compare1" value='.$row['secondaryID'].' />';
				$temp["Compare 2"] = '<input type="radio" name="compare2" value='.$row['secondaryID'].' />';
			}

			foreach ($displayFields as $I=>$V) { // foreach 1

				if ($firstItem === TRUE) {
					$tableHeaders[] = $V['label'];
				}

				if (isset($metadata[$V['field']])) {
					$value = $metadata[$V['field']];
				}
				else if (isset($row[$V['field']])) {
					$value = $row[$V['field']];
				}
				else {
					$value ="";
				}

				if (isset($V['translation'])) {
					if (is_array($V['translation'])) {
						if (isset($V['translation'][$value])) {
							$value = $V['translation'][$value];
						}
						} // is array
						else if (is_function($V['translation'])) {
							$value = $V['translation']($value);
						}
					}

					$temp[$V['label']] = $value;
			} // foreach 1
			$revArray[] = $temp;
			$firstItem  = FALSE;
		} // while 1

		$table = new tableObject("array");

		$table->summary = "Revisions Table";
		$table->sortable = FALSE;
		$table->headers($tableHeaders);

		return($table->display($revArray));

	}

	/**
	 * Display the comparison of the 2 provided revision IDs
	 *
     * ###Fields Array:
     * Array of how to compare the fields. If nothing is provided, just displays the fields side by side with default diff tool<br>
     * Note: custom diff function is NOT run through htmlSanitize before display, return from function should be sanitized
     *
     * - $fields['metadata']['fieldName']['display'] = create_function(); // used to display data (takes 1 arg)
     * - $fields['metadata']['fieldName']['diff']    = create_function(); // used to perform diff (takes 2 args)
     * - $fields['relatedData']['fieldName']         = create_function(); // used to translate value (takes 1 arg)
     * - $fields['digitalObjects']                   = create_function(); // used to display the digital object (or provide links), takes 1 arg
     *
	 * @param string $primaryIDValue_1
	 *        Primary id of first item to compare
	 * @param string $secondaryIDValue_1
	 *        Secondary id of first item to compare
	 * @param string $primaryIDValue_2
	 *        Primary id of second item to compare
	 * @param string $secondaryIDValue_2
	 *        Secondary id of second item to compare
	 * @param array $fields
	 *        Array of how to compare the fields. *See section above*
	 * @return bool|string
	 */
	public function compare($primaryIDValue_1, $secondaryIDValue_1, $primaryIDValue_2, $secondaryIDValue_2, $fields=NULL) {

		// Get the first item
		$sql = sprintf("SELECT * FROM `%s` WHERE `productionTable`=? AND `primaryID`=? AND `secondaryID`=?",
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($this->productionTable, $primaryIDValue_1, $secondaryIDValue_1));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error retrieving first item", errorHandle::DEBUG);
			return(FALSE);
		}

		$row_1                   = $sqlResult->fetch();
		$row_1['metadata']       = $this->getMetadataForID($row_1['ID']);
		$row_1['relatedData']    = $this->getMetadataForID($row_1['ID'],"relatedData");
		$row_1['digitalObjects'] = $this->getMetadataForID($row_1['ID'],"digitalObjects",FALSE);

		// Get the second item
		$sql = sprintf("SELECT * FROM `%s` WHERE `productionTable`=? AND `primaryID`=? AND `secondaryID`=?",
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($this->productionTable, $primaryIDValue_1, $secondaryIDValue_2));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Error retrieving second item", errorHandle::DEBUG);
			return(FALSE);
		}

		$row_2                   = $sqlResult->fetch();
		$row_2['metadata']       = $this->getMetadataForID($row_2['ID']);
		$row_2['relatedData']    = $this->getMetadataForID($row_2['ID'],"relatedData");
		$row_2['digitalObjects'] = $this->getMetadataForID($row_2['ID'],"digitalObjects",FALSE);

		$output  = sprintf('<table class="engineRCSCompareTable" id="engineRCSCompareTable_%s">',
			htmlSanitize($this->productionTable)
			);

		$output .= '<tr>';
		$output .= '<th>Field Name</th>';
		$output .= sprintf('<th>%s</th>',date("m/d/Y H:i:s",$row_1['secondaryID']));
		$output .= sprintf('<th>%s</th>',date("m/d/Y H:i:s",$row_2['secondaryID']));
		$output .= '</tr>';

		foreach ($row_1['metadata'] as $I=>$V) {

			if (isset($fields['metadata'][$I]['display'])) {
				$convertedResult_1 = $fields['metadata'][$I]['display']($row_1['metadata'][$I]);
				$convertedResult_2 = $fields['metadata'][$I]['display']($row_2['metadata'][$I]);
			}
			else {
				$convertedResult_1 = htmlSanitize($row_1['metadata'][$I]);
				$convertedResult_2 = htmlSanitize($row_2['metadata'][$I]);
			}

			if (isset($fields['metadata'][$I]['diff'])) {
				$diff = $fields['metadata'][$I]['diff']($row_1['metadata'][$I],$row_2['metadata'][$I]);
			}
			else {
				$diff = htmlDiff(htmlSanitize($row_1['metadata'][$I]),htmlSanitize($row_2['metadata'][$I]));
			}

			$output .= '<tr>';
			$output .= sprintf('<td rowspan="2" class="fieldName">%s</td>',$I);
			$output .= sprintf('<td><code>%s</code></td>',$convertedResult_1);
			$output .= sprintf('<td><code>%s</code></td>',$convertedResult_2);
			$output .= '</tr>';

			$output .= '<tr>';
			$output .= sprintf('<td colspan="2"><code>%s</code></td>',$diff);
			$output .= '</tr>';

		}

		if (!is_empty($row_1['relatedData'])) {
			$relatedData_1 = '<ul class="engineRCSCompareTable_UL_1">';
			foreach ($row_1['relatedData'] as $tableName=>$V) {
				$relatedData_1 .= sprintf('Table: <strong>%s</strong><ul>',htmlSanitize($tableName));

				foreach ($V as $I=>$fieldRow) {
					$relatedData_1 .= "<li>Row: <ul>";
					foreach ($fieldRow as $fieldName=>$fieldValue) {
						if (isset($fields['relatedData'][$fieldName])) {
							$convertedResult = $fields['relatedData'][$fieldName]($row_1['relatedData'][$tableName][$I][$fieldName]);
						}
						else {
							$convertedResult = $row_1['relatedData'][$tableName][$I][$fieldName];
						}
						$relatedData_1 .= sprintf("<li>%s : %s</li>",htmlSanitize($fieldName),htmlSanitize($convertedResult));
					}
					$relatedData_1 .= "</ul></li>";
				}

			}
			$relatedData_1 .= '</ul>';
		}

		if (!is_empty($row_2['relatedData'])) {
			$relatedData_2 = '<ul class="engineRCSCompareTable_UL_2">';
			foreach ($row_2['relatedData'] as $tableName=>$V) {
				$relatedData_2 .= sprintf('Table: <strong>%s</strong><ul>',htmlSanitize($tableName));

				foreach ($V as $I=>$fieldRow) {
					$relatedData_2 .= "<li>Row: <ul>";
					foreach ($fieldRow as $fieldName=>$fieldValue) {
						if (isset($fields['relatedData'][$fieldName])) {
							$convertedResult = $fields['relatedData'][$fieldName]($row_2['relatedData'][$tableName][$I][$fieldName]);
						}
						else {
							$convertedResult = $row_1['relatedData'][$tableName][$I][$fieldName];
						}
						$relatedData_2 .= sprintf("<li>%s : %s</li>",htmlSanitize($fieldName),htmlSanitize($convertedResult));
					}
					$relatedData_2 .= "</ul></li>";
				}

			}
			$relatedData_2 .= '</ul>';
		}

		if (!is_empty($relatedData_1) || !is_empty($relatedData_2)) {
			$output .= '<tr>';
			$output .= '<td class="fieldName">Related Data</td>';
			$output .= sprintf('<td>%s</td>',$relatedData_1);
			$output .= sprintf('<td>%s</td>',$relatedData_2);
			$output .= '</tr>';
		}

		if (!is_empty($row_1['digitalObjects']) || !is_empty($row_2['digitalObjects'])) {

			if (isset($fields['digitalObjects'])) {
				$digitalObjects_1 = $fields['digitalObjects']($row_1['digitalObjects']);
				$digitalObjects_2 = $fields['digitalObjects']($row_2['digitalObjects']);
			}
			else {
				$digitalObjects_1 = $row_1['digitalObjects'];
				$digitalObjects_2 = $row_2['digitalObjects'];
			}

			$output .= '<tr>';
			$output .= '<td class="fieldName">Digital Objects</td>';
			$output .= sprintf('<td>%s</td>',$digitalObjects_1);
			$output .= sprintf('<td>%s</td>',$digitalObjects_2);
			$output .= '</tr>';
		}

		$output .= '</table>';

		return($output);
	}

	/**
	 * Add a field to be excluded from revision control
	 *
	 * @param string $fieldName
	 * @return bool
	 */
	public function addExcludedField($fieldName) {
		$this->excludeFields[] = $fieldName;
		return(TRUE);
	}

	/**
	 * Remove a field from the excluded list
	 *
	 * @param $fieldName
	 * @return bool
	 */
	public function removeExcludedField($fieldName) {
		if (isset($this->excludeFields[$fieldName])) {
			unset($this->excludeFields[$fieldName]);
			return(TRUE);
		}
		return(FALSE);
	}

	/**
	 * Clear list of excluded fields
	 *
	 * @return bool
	 */
	public function clearExcludedFields() {
		unset($this->excludeFields);
		$this->excludeFields = array();
		return(TRUE);
	}

	/**
	 * Related data allows revision control to keep track of revisions in other tables.
	 *
	 * @param $table
	 *        The table where the related data is stored
	 * @param $primaryKey
	 *        This is where the primary key of the main object (as passed into insertRevision)
	 * @return bool
	 */
	public function addRelatedDataMapping($table,$primaryKey) {

		// does the table exist?
		$sql       = sprintf("select 1 from `%s`",
			$this->db->escape($table)
			);
		$sqlResult = $this->db->query($sql);

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - Invalid table", errorHandle::DEBUG);
			return(FALSE);
		}

		$this->relatedMappings[$table] = array(
			"table" 	 => $table,
			"primaryKey" => $primaryKey
			);

		return(TRUE);

	}

	/**
	 * Renames production tables in the revision table in the event that the name of a production
	 * WARNING: This method is not yet implemented!
	 *
	 * @todo finish the method
	 * @param $oldTableName
	 * @param $newTableName
	 */
	public function updateTableName($oldTableName,$newTableName) {

	}

	/**
	 * @todo finish the method
	 * @param $table
	 * @param $command
	 * @param $search
	 * @param $replace
	 */
	public function updateRevisionTableStructure($table,$command,$search,$replace) {

	}

    /**
     * Returns a revision ID number
     *
     * This method will return the specified revision ID from the revisions table.<br>
     *
     * @author Michael Bond
     * @param string $primaryID
     *        The primary ID for the object under revision control
     * @param string $secondaryID
     *        The secondary ID for the object under revision control
     * @return array|bool
     */
    public function getRevisionID($primaryID,$secondaryID) {

    	$sql = sprintf("SELECT `ID` FROM `%s` WHERE productionTable=? AND primaryID=? AND secondaryID=? LIMIT 1",
            $this->db->escape($this->revisionTable),
            $this->db->escape($this->productionTable),
            $this->db->escape($primaryID),
            $this->db->escape($secondaryID)
        );
        $sqlResult = $this->db->query($sql, array($this->productionTable, $primaryID, $secondaryID));

		if($sqlResult->errorCode()){
            errorHandle::newError(__METHOD__."() - SQL Error: ".$sqlResult->errorMsg(), errorHandle::DEBUG);
            return FALSE;
        }
        else {
            $row = $sqlResult->fetch();
            return $row['ID'];
        }

        return FALSE;

    }

    /**
     * Returns the revision table
     *
     * @author Michael Bond
     * @return string
     */
    public function getRevisionTable() {
		return $this->revisionTable;
	}

    /**
     * Returns the production table
     *
     * @author Michael Bond
     * @return string
     */
	public function getProductionTable() {
		return $this->productionTable;
	}

    /**
     * Retrieves an array of secondaryIDs
     *
     * This method will return an array of the recorded secondary IDs for a given production object.
     *
     * @author David Gersting
     * @param string $primaryID
     *        The primary ID of the object
     * @param string $orderByDirection
     *        The ORDER BY direction to apply (Valid: ASC or DESC)
     * @param string $where
     *        An optional WHERE clause to be added to the SQL call
     * @return array
     */
    public function getSecondaryIDs($primaryID,$orderByDirection='ASC',$where=NULL){
        $where   = (isset($where) and !empty($where)) ? " AND ($where)" : '';

        // Format and validate $orderByDirection
        $orderByDirection = trim(strtoupper($orderByDirection));
        if($orderByDirection != 'ASC' and $orderByDirection != 'DESC'){
            errorHandle::newError(__METHOD__."() - Invalid param for orderByDirection: '$orderByDirection' (Only 'ASC' and 'DESC' allowed)", errorHandle::DEBUG);
            $orderByDirection = 'ASC';
        }

        // Build and run SQL
        $sql = sprintf("SELECT secondaryID FROM `%s` WHERE (productionTable=? AND primaryID=?) %s ORDER BY secondaryID %s",
            $this->db->escape($this->revisionTable),
            $this->db->escape($where),
            $this->db->escape($orderByDirection)
        );
        $sqlResult = $this->db->query($sql, array($this->productionTable, $primaryID));

        // Did it work?
		if ($sqlResult->errorCode()){
            errorHandle::newError(__METHOD__."() - SQL Error: ".$sqlResult->errorMsg(), errorHandle::DEBUG);
			return array();
        }else{
			return $sqlResult->fetchFieldAll();
        }
    }

	/**
	 * Retrieved metadata for given revision
	 * @param $revisionID
	 * @param string $type
	 * @param bool $decode
	 * @return bool|mixed|string
	 *         Returns empty string if nothing is found
	 */
	public function getMetadataForID($revisionID,$type="metadata",$decode=TRUE) {

		if (!validate::integer($revisionID)) {
			errorHandle::newError(__METHOD__."() - invalid ID passed for revisionID", errorHandle::DEBUG);
			return(FALSE);
		}

		$sql       = sprintf("SELECT `%s` FROM `%s` WHERE `ID`=?",
			$this->db->escape($type),
			$this->db->escape($this->revisionTable)
			);
		$sqlResult = $this->db->query($sql, array($revisionID));

		if ($sqlResult->errorCode()) {
			errorHandle::newError(__METHOD__."() - retrieving row ".$type, errorHandle::DEBUG);
			return(FALSE);
		}

		$row = $sqlResult->fetch();

		// Retrieve metaData if it is a link
		if (validate::integer($row[$type])) {
			$sql       = sprintf("SELECT `%s` FROM %s WHERE `ID`=?",
				$this->db->escape($type),
				$this->db->escape($this->revisionTable)
				);

			$sqlResult = $this->db->query($sql, array($row[$type]));

			if ($sqlResult->errorCode()) {
				errorHandle::newError(__METHOD__."() - retrieving linked ".$type, errorHandle::DEBUG);

				// roll back database transaction
				$this->db->rollback();

				return(FALSE);
			}

			$row2        = $sqlResult->fetch();
			$row[$type]  = $row2[$type];

		}

		if (is_empty($row[$type])) {
			return("");
		}

		if ($decode === FALSE) {
			return($row[$type]);
		}

		return(unserialize(base64_decode($row[$type])));

	}

}

?>