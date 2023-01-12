<?php

$this->all(":404", function() {
  $this->send("404 - not found (custom ALL)!", 404);
});

$this->render_html(":404", function() { ?><p>Hello <strong>HTML 404</strong> here!</p><?php }, [], 404);

$this->post(":404", function() {
  $this->send("404 - not found (custom POST)!", 404);
});

?>