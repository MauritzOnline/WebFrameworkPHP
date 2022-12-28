<?php

require_once("../WebFramework.php");

$webFramework = new WebFramework();
$webFramework->debug_mode = true; // use this if you want more detailed messages (not recommended for production)
$webFramework->parse_auth(); // activate parsing of Bearer token
$webFramework->start();

?>