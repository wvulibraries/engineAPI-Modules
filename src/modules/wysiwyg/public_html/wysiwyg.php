<?php

require_once '/path/to/engine.php';
$engine = EngineAPI::singleton();

$wysiwyg = new wysiwyg();
$wysiwyg->baseURL = '/wysiwyg';
$wysiwyg->handleRequest();
