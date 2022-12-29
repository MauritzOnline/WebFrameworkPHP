<?php

$this->post("/post_data", function() {
  return $this->send_json($this->request->body, false);
});

?>