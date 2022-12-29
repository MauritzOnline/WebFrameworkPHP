<?php

$this->get("/download_file", function() {
  return $this->send_file("test_files/to_download.txt", content_type: "text/plain");
});

$this->get("/download_file/stream", function() {
  return $this->send_file("test_files/to_download.txt", content_type: "text/plain", stream: true);
});

$this->post("/upload_file", function() {
  if(empty($this->request->files)) {
    return $this->send_json(array(
      "error" => "No files could be found in request!"
    ), false);
  }

  if(count($this->request->files) > 1) {
    return $this->send_json(array(
      "error" => "Too many files in request, only 1 is expected!"
    ), false);
  }

  if(!isset($this->request->files["file1"])) {
    return $this->send_json(array(
      "error" => "Couldn't find the uploaded file under the property \"file1\"!"
    ), false);
  }
  
  $file = $this->request->files["file1"];
  if($file["error"] !== UPLOAD_ERR_OK) {
    return $this->send_json(array(
      "error" => "An error occurred with the upload!"
    ), false);
  }

  // Get the temporary file path
  $tmp_file = $file["tmp_name"];

  // Generate a new file name
  $new_file_name = "uploaded_file" . pathinfo($file["name"], PATHINFO_EXTENSION);

  // Set the target file path
  $target_file = "test_files/" . $new_file_name;

  // Move the file from the temporary location to the target location
  if (move_uploaded_file($tmp_file, $target_file)) {
    $file_contents = file_get_contents($target_file);
    unlink($target_file);

    if($file_contents === false) {
      return $this->send_json(array(
        "error" => "Failed to read uploaded file!"
      ), false);
    }

    return $this->send_json(array_merge($this->request->body, array("file1" => $file_contents)), false);
  } else {
    return $this->send_json(array(
      "error" => "Failed to move uploaded file!"
    ), false);
  }
});

?>