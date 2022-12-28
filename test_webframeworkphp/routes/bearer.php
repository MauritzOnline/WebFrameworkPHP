<?php

$this->get("/bearer", function() {
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

?>