<?php

$this->get("/auth/bearer", function() {
  $this->send($this->request->token);
}, array("auth" => true));

$this->get("/auth/basic", function() {
  $credentials = $this->request->credentials;
  $this->send('username: "' . $credentials["username"] . '", password: "' . $credentials["password"] . '"');
}, array("auth" => true, "use_basic_auth" => true));

?>