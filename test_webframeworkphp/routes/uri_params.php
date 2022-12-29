<?php

$this->get("/uri_params/json/:param1", function() {
  $this->send_json_body(array(
    "status" => 200,
    "param1" => $this->request->params["param1"],
    "query" => $this->request->query
  ));
});

$this->get("/uri_params/json/:param1/:param2", function() {
  $this->send_json_body(array(
    "status" => 200,
    "param1" => $this->request->params["param1"],
    "param2" => $this->request->params["param2"],
    "query" => $this->request->query
  ));
});

$this->render_html("/uri_params/html/:param1", function() { ?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hello world!</title>
  </head>

  <body>
    <p class="param1"><?php echo $this->request->params["param1"]; ?></p>
    <pre class="query"><?php echo json_encode($this->request->query); ?></pre>
  </body>
</html>
<?php });

$this->render_html("/uri_params/html/:param1/:param2", function() { ?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hello world!</title>
  </head>

  <body>
    <p class="param1"><?php echo $this->request->params["param1"]; ?></p>
    <p class="param2"><?php echo $this->request->params["param2"]; ?></p>
    <pre class="query"><?php echo json_encode($this->request->query); ?></pre>
  </body>
</html>
<?php });

?>