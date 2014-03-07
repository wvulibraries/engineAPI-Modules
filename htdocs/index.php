<?php
error_reporting(E_ALL);



// Load engine core
require '/tmp/git/engineAPI/engine/engineAPI/latest/engine.php';
$engine = EngineAPI::singleton();
errorHandle::errorReporting(errorHandle::E_ALL);

echo '<pre>'.print_r(session::$sessionData['csrf'], true).'</pre>';
//session::reset();
//exit();
//echo '<pre>'.print_r($_SESSION, true).'</pre>';

// Load extra engine modules
$modulesBase = '/vagrant/src/modules';
$dirHandle = opendir($modulesBase);
while (false !== ($file = readdir($dirHandle))) {
	if($file[0] == '.') continue;
	$file = "$modulesBase/$file";
	if(is_dir($file)) autoloader::getInstance()->addLibrary($file);
}

// Create out appDB connection
db::getInstance()->create('mysql', array(
	'dsn'    => 'mysql:dbname=engineCMS;host=localhost',
	'user'   => 'root',
	'pass'   => '',
	'dbname' => 'helpdesk',
), 'appDB');

// ==============================================================
echo 'Hello!';
if(sizeof($_POST)) var_dump(formBuilder::process());



$form = formBuilder::createForm('testForm');
$form->linkToDatabase(array(
	'table' => 'departments'
));

$form->addField(array(
	'name'=>'name',
	'type'=>'text'
));
$form->addField(array(
	'name'=>'email',
	'type'=>'email'
));
$form->addField(array(
	'name'=>'visible',
	'type'=>'boolean',
	'options' => array(
		'type' => 'check',
//		'includeBlank' => TRUE,
		'labels' => array('False','True')
	)
));
$form->addField(array(
	'name'=>'sendTo',
	'type'=>'text'
));
$form->addField(array(
	'name'=>'projects',
	'type'=>'text'
));

echo $form->display('insert',array());
//echo $form->display('assets',array()); exit();
?>
<html>
<head>
	{form name="testForm" display="assets"}
	<style>
		.radioLabel{
			display: block;
			cursor: pointer;
		}
		.checkboxLabel{
			display: block;
			cursor: pointer;
		}
		label{
			display: block;
			cursor: pointer;
		}
	</style>
</head>
<body>
<?php
echo md5(print_r($_SESSION, true))."<br>";
echo md5(print_r(session::$sessionData, true))."<br>";
foreach(session::$sessionData['csrf'] as $key => $val){
	echo $key."<br>";
}
echo session_id();
?>
</body>
</html>