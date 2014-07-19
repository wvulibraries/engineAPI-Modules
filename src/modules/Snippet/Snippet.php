<?php
/**
 * EngineAPI Snippet module
 *
 * @todo Add phpDoc blocks
 * @package EngineAPI\modules\Snippet
 */
class Snippet {

	/**
	 * @var dbDriver
	 */
	private $db;
	private $engine           = NULL;
	private $table            = NULL;
	private $field            = NULL;
	private $metaFields       = NULL;
	private $hiddenMetaFields = NULL;
	
	public $resultURL         = NULL;
	public $textSubmitButton  = "Submit";
	public $textPreviewButton = "Preview";
	public $textResetButton   = "Reset";
	public $snippetURL        = "/snippet.php?id=";
	public $snippetPublicURL  = "/snippetPublic.php?id=";
	
	public $pattern           = "/\{snippet\s+(.+?)\}/";
	public $function          = "Snippet::templateMatches";

	/**
	 * @param string $table
	 *        Database table where snippets are stored
	 * @param string $field
	 *        Database table field where snippets are stored
	 * @param dbDriver|string $db
	 *        Database connect to use
	 *
	 */
	function __construct($table,$field=NULL,$db=NULL) {
		$this->engine    = EngineAPI::singleton();

		$this->set_database($db);

		$this->table     = $this->db->escape($table);
		$this->field     = $this->db->escape($field);
		
		templates::defTempPatterns($this->pattern,$this->function,$this);
		
		// setup default result URL for snippetList

		// this action thing was purposed for the ereserves software. this needs removed and made more generic
		if (isset($_GET['HTML']['action'])) {
			$this->resultURL = $_SERVER['PHP_SELF']."?action=".$_GET['HTML']['action'];
		}
		else {
			$this->resultURL = $_SERVER['PHP_SELF']."?action=whatever";
		}
	}
	
	function __destruct() {
	}

	/**
	 * Set the internal database connection
	 * @param dbDriver|string $db
	 */
	public function set_database($db=NULL){
		if(isnull($db)) $db = 'appDB';
		$this->db = $db instanceof dbDriver
			? $db
			: db::get($db);
	}

	/**
	 * Engine template tag handler for {snippet id="" field=""}
	 * @param array $matches
	 * @return string
	 */
	public static function templateMatches($matches) {

		$snippet  = templates::retTempObj("Snippet");
		$attPairs = attPairs($matches[1]);

		$output   = "Error in snippet.php";

		if (isset($attPairs['id']) && isset($attPairs['field'])) {
			$output = $snippet->display($attPairs['id'],$attPairs['field']);
		}

		return($output);

	}
	
	/**
	 * Generate an HTML list of snippets
	 *
	 * @param string $class
	 *        The CSS class that is applied to the snippet list.
	 * @param string $type
	 *        Type of list to generate
	 *          - ol: ordered List
	 *          - ul: unordered List
	 *          - li: list elements (no parent OL or UL tags)
	 *          - br: <br /> separated <a>'s
	 * @param bool $collapse
	 *        If True, will generate collapsible snippets
	 * @param bool $showURL
	 *
	 * @return string
	 */
	public function insertSnippetList($class="we_snippetList",$type="ul",$collapse=FALSE,$showURL=FALSE) {
		$sql       = sprintf("SELECT * FROM %s ORDER BY snippetName", $this->table);
		$sqlResult = $this->db->query($sql);
		if ($sqlResult->error()) {
			errorHandle::newError(__METHOD__."() - ".$sqlResult->errorMsg(), errorHandle::DEBUG);
			return errorHandle::errorMsg("Error fetching snippets.");
		}
		
		?>
		<script type="text/javascript">
			function snippetAlert(id) {
				var txt = '<?php enginevars::getInstance()->get("WEBROOT").$this->snippetURL ?>'+id+'\n{snippet id='+id+'}';
				alert(txt);
			}
		</script>
		<?php
		
		$output = "";
		
		if ($type == "ul" || $type == "ol") {
			if ($collapse === FALSE) {
				$output .= "<".$type." class=\"".$class."\">";
			}
			else {
				$output .= "<span onclick=\"toggleMenu('".$class."');\" class=\"toggleLink\"><img src=\"".enginevars::getInstance()->get("imgListRetractedIcon")."\" id=\"".$class."_img\" width=\"8px\" height=\"8px\" /> Snippet List</span>";
				$output .= "<".$type." id=\"".$class."\" class=\"".$class."\">";
			}
		}
		
		// $jsOutput is built here and inserted in the javascript below
		// we need each snippet entry to be in the array for the info toggle to work
		$jsOutput = "snippetInfoArray['".$class."'] = new Array();\n";
		while ($row = $sqlResult->fetch()) {
			
			$jsOutput .= "snippetInfoArray['".$class."'][\"".$row['ID']."_snippet\"] = \"false\";\n";
			
			if ($type == "ul" || $type == "ol" || $type == "li") {
				$output .= "<li>";
			}
			$output .= "<span class=\"deleteSpan\"><a href=\"".$this->resultURL."&amp;deleteID=".$row['ID']."\" onclick=\"return engineDeleteConfirm('".htmlsanitize($row['snippetName'])."');\"><img src=\"".enginevars::getInstance()->get("imgDeleteIcon")."\" alt=\"delete\"  style=\"cursor: not-allowed;\"/></a></span>";
			$output .= "&nbsp;";
			if ($showURL === TRUE && $collapse === TRUE) {
				$output .= "<span onclick=\"toggleSnippetInfo('".$row['ID']."_snippet');\" class=\"toggleLink\"><img style=\"cursor: help;\" src=\"".enginevars::getInstance()->get("imgListRetractedIcon")."\" id=\"".$row['ID']."_snippet_img\" /> </span>";
			}
			$output .= "<a href=\"".$this->resultURL."&amp;snippetID=".htmlsanitize($row['ID'])."\">".htmlsanitize($row['snippetName'])."</a>";
			if ($showURL === TRUE && $collapse === TRUE) {
				$output .= "<".$type." id=\"".$row['ID']."_snippet\" style=\"display:none\">";
				$output .= "<li>Auth Required: <a href=\"".enginevars::getInstance()->get("WEBROOT").$this->snippetURL.$row['ID']."\">".enginevars::getInstance()->get("WEBROOT").$this->snippetURL.$row['ID']."</a></li>";
				$output .= "<li>Public: <a href=\"".enginevars::getInstance()->get("WEBROOT").$this->snippetPublicURL.$row['ID']."\">".enginevars::getInstance()->get("WEBROOT").$this->snippetPublicURL.$row['ID']."</a></li>";
				$output .= "<li>{snippet field=\"".$this->field."\" id=\"".$row['ID']."\"}</li>";
				$output .= "</".$type.">";
			}
			if ($type == "ul" || $type == "ol" || $type == "li") {
				$output .= "</li>";
			}
			else {
				$output .= "<br />";
			}
		}
		if ($type == "ul" || $type == "ol") {
			$output .= "</".$type.">";
		}
		
		$output .= "
			<script type=\"text/javascript\">
				var ID = '$class';
				
				var temp = document.getElementById(ID);
				if ($.cookie(ID) == null) {
					$.cookie(ID, \"false\", { path: '/admin' });
					temp.style.display = \"none\";
				}
				else {
					visible[ID] = $.cookie(ID);
					if (visible[ID] == \"true\") {
						temp.style.display = \"block\";
						var img = document.getElementById(ID+\"_img\");
						img.src=\"".enginevars::getInstance()->get("imgListExpandedIcon")."\";
					}
					else {
						temp.style.display = \"none\";
						var img = document.getElementById(ID+\"_img\");
						img.src=\"".enginevars::getInstance()->get("imgListRetractedIcon")."\";
					}
				}
			</script>";
			
		$output .= "
		<script type=\"text/javascript\">
		
		var ID = '$class';
		
		if (window.snippetInfoArray === undefined) {
			var snippetInfoArray = new Array();
		}
		";
		
		$output .= $jsOutput;
	
		$output .= "
		function toggleSnippetInfo(id) {			
			if (snippetInfoArray[ID][id] == \"false\") {
				$('#'+id+'').show('slow');
				snippetInfoArray[ID][id] = \"true\";
				$.cookie(id, \"true\");
				var img = document.getElementById(id+\"_img\");
				img.src=\"".enginevars::getInstance()->get("imgListExpandedIcon")."\";
			}
			else { 
				$('#'+id+'').hide('slow');
				snippetInfoArray[ID][id] = \"false\";
				$.cookie(id, \"false\");
				var img = document.getElementById(id+\"_img\");
				img.src=\"".enginevars::getInstance()->get("imgListRetractedIcon")."\";
			}
		}
		</script>
		";

		return($output);
	}

	/**
	 * Display a snippet from the database
	 *
	 * @todo optimize SQL
	 * @param int $id
	 * @param string $field
	 * @return string
	 */
	public function display($id,$field) {

		$sql       = sprintf("SHOW INDEXES FROM %s", $this->table);
		$sqlResult = $this->db->query($sql);
		if ($sqlResult->error()) {
			return errorHandle::errorMsg("Error fetching primary key.");
		}
		
		$row = $sqlResult->fetch();
		$key = $row['Column_name'];
		
		$sql = sprintf("SELECT * FROM %s WHERE %s='%s'",
			$this->table,
			$key,
			$this->db->escape($id)
			);
		$sqlResult = $this->db->query($sql);
		if ($sqlResult->error()) {
			return errorHandle::errorMsg("Error fetching snippet.");
		}
		
		$row = $sqlResult->fetch();
		
		return($row[$field]);
	}

	/**
	 * Delete a snippet from the database
	 *
	 * @todo optimize SQL
	 * @param int $id
	 * @return string
	 */
	public function delete($id) {

		$sql       = sprintf("SHOW INDEXES FROM %s", $this->table);
		$sqlResult = $this->db->query($sql);
		if ($sqlResult->error()) {
			return errorHandle::errorMsg("Error fetching primary key.");
		}
		
		$row = $sqlResult->fetch();
		$key = $row['Column_name'];
		
		$sql = sprintf("DELETE FROM %s WHERE %s='%s'",
			$this->table,
			$key,
			$id
			);

		$sqlResult = $this->db->query($sql);
		if ($sqlResult->error()) {
			return errorHandle::errorMsg("Error fetching snippet.");
		}
		
		return errorHandle::successMsg("Successfully Deleted Snippet");
		
	}
}

?>