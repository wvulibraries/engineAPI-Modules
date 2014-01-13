<?php
define('ENGINE_BASE', '/tmp/git/engineAPI');
date_default_timezone_set('UTC');

require_once ENGINE_BASE.'/engine/engineAPI/latest/engine.php';
EngineAPI::singleton();
//errorHandle::errorReporting(errorHandle::E_ALL);

$modulesBase = __DIR__.'/../src/modules';
$dirHandle = @opendir($modulesBase);
while (false !== ($file = readdir($dirHandle))) {
    if($file[0] == '.') continue;
    $file = "$modulesBase/$file";
    if(is_dir($file)) autoloader::getInstance()->addLibrary($file);
}

?>
