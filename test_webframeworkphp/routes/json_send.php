<?php

$this->post("/send_json", function() {
  $run_body_version = false;
  $include_status_code = true;
  $status_code = 0;

  if(isset($this->request->query["run_body_version"])) {
    $cleaned_run_body_version = trim(strtolower($this->request->query["run_body_version"]));

    switch($cleaned_run_body_version) {
      case "0":
      case "false":
        $run_body_version = false;
        break;

      case "1":
      case "true":
        $run_body_version = true;
        break;

      default:
        return $this->send_json(array("error" => "Invalid run_body_version query!"), 400);
    }
  }

  if(isset($this->request->query["include_status_code"])) {
    $cleaned_include_status_code = trim(strtolower($this->request->query["include_status_code"]));

    switch($cleaned_include_status_code) {
      case "0":
      case "false":
        $include_status_code = false;
        break;

      case "1":
      case "true":
        $include_status_code = true;
        break;

      default:
        return $this->send_json(array("error" => "Invalid include_status_code query!"), 400);
    }
  }

  if(isset($this->request->query["status_code"])) {
    $cleaned_status_code = intval(trim($this->request->query["status_code"]));
    if($cleaned_status_code < 100 || $cleaned_status_code > 599) {
      return $this->send_json(array("error" => "Invalid status_code query (must be 100-599)!"), 400);
    }

    $status_code = $cleaned_status_code;
  }

  $final_data = $this->request->body;

  if($run_body_version === true) {
    $final_data = (object) array_merge(array(
      "status" => $status_code
    ), (array) $final_data);

    return $this->send_json_body($final_data, $include_status_code);
  } else {
    return $this->send_json($this->request->body, $status_code, $include_status_code);
  }
});

?>