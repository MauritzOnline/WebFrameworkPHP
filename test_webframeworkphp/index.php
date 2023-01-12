<?php

require_once("../WebFramework.php");

$webFramework = new WebFramework(array(
  "debug_mode" => true, // use this if you want more detailed messages (not recommended for production)
  "include_status_code_in_json" => false
));
$webFramework->parse_auth(); // activate parsing of Bearer token
$webFramework->start();

?>