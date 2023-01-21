<?php

$this->get("/download_file", function() {
  return $this->send_file("test_files/to_download.txt", content_type: "text/plain");
});

$this->get("/download_file/stream", function() {
  return $this->send_file("test_files/to_download.txt", content_type: "text/plain", stream: true);
});

$this->post("/upload_file", function() {
  try {
    $uploaded_file = $this->move_uploaded_file("file1", "test_files/uploaded");

    $file_contents = str_replace("\r", "", file_get_contents($uploaded_file));
    unlink($uploaded_file);

    if($file_contents === false) {
      return $this->send_json(array(
        "error" => "Failed to read uploaded file!"
      ));
    }

    return $this->send_json(array_merge($this->request->body, array("file1" => $file_contents, "uploaded_file" => $uploaded_file)));
  } catch(Exception $err) {
    return $this->send_json(array(
      "error" => $err->getMessage()
    ));
  }
});

$this->post("/upload_file/options", function() {
  try {
    $uploaded_file = $this->move_uploaded_file("file1", "test_files/uploaded", array(
      "new_file_name" => "uploaded_file",
      "new_file_ext" => "md",
      "allowed_exts" => array("txt"),
      "min_size" => (10 * 1024), // 10 KiB
      "max_size" => (50 * 1024), // 50 KiB
    ));
    
    $file_contents = str_replace("\r", "", file_get_contents($uploaded_file));
    unlink($uploaded_file);

    if($file_contents === false) {
      return $this->send_json(array(
        "error" => "Failed to read uploaded file!"
      ));
    }

    return $this->send_json(array_merge($this->request->body, array("file1" => $file_contents, "uploaded_file" => $uploaded_file)));
  } catch(Exception $err) {
    return $this->send_json(array(
      "error" => $err->getMessage()
    ));
  }
});

?>