<?php

$this->get("/route_args/json", function() {
  $this->send_json($this->route->args);
}, array("arg1" => true, "arg2" => "123abc"));

$this->render_html("/route_args/html", function() { ?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hello world!</title>
  </head>

  <body>
    <p class="arg1"><?php echo $this->route->args["arg1"]; ?></p>
    <p class="arg2"><?php echo $this->route->args["arg2"]; ?></p>
  </body>
</html>
<?php }, array("arg1" => true, "arg2" => "123abc"));

?>