<?php

require_once("../WebFramework.php");

$webFramework = new WebFramework(array(
  "debug_mode" => true, // use this if you want more detailed messages (not recommended for production)
  "include_status_code_in_json" => false
));

$webFramework->start(function() use($webFramework) {
  $route_args = $webFramework->route->args;
  $is_auth_required = (isset($route_args["auth"]) ? $route_args["auth"] : false);
  $use_basic_auth = (isset($route_args["use_basic_auth"]) ? $route_args["use_basic_auth"] : false);

  if($is_auth_required === true) {
    $webFramework->parse_auth(); // this can either be placed here or anytime before the call of "start()" (just make sure do not add it in two places)

    if($use_basic_auth) {
      $credentials = $webFramework->request->credentials;
      $users = array(
        "john.doe" => "password",
        "john" => "doe:pass:word"
      );

      if($credentials === null) {
        $webFramework->send('Missing valid auth credentials!');
      }

      $found_user_pass = (isset($users[$credentials["username"]]) ? $users[$credentials["username"]] : null);
      if($found_user_pass !== $credentials["password"]) {
        $webFramework->send("Invalid auth credentials!");
      }
    } else {
      if($webFramework->request->token === null) {
        $webFramework->send("Missing valid auth token!"); // stops any route from being run
      }
      if($webFramework->request->token !== "my_valid_secret_token") {
        $webFramework->send("Invalid auth token!"); // stops any route from being run
      }
    }
  }
});

?>