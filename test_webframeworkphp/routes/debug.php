<?php

$this->get("/debug", function() {
  $this->send_json($this->get_debug_info());
});

?>