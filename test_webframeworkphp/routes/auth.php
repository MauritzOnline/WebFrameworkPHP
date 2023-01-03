<?php

$this->get("/auth/bearer", function() {
  if($this->request->token === null) {
    $this->send("Missing valid auth token!");
  } else {
    if($this->request->token === "my_valid_secret_token") {
      $this->send($this->request->token);
    } else {
      $this->send("Invalid auth token!");
    }
  }
});

$this->get("/auth/basic", function() {
  $credentials = $this->request->credentials;

  $users = array(
    "john.doe" => "password",
    "john" => "doe:pass:word"
  );

  if($credentials === null) {
    $this->send('Missing valid auth credentials!');
  } else {
    if(isset($users[$credentials["username"]]) && $users[$credentials["username"]] === $credentials["password"]) {
      $this->send('username: "' . $credentials["username"] . '", password: "' . $credentials["password"] . '"');
    } else {
      $this->send("Invalid auth credentials!");
    }
  }
});

?>