<?php
/**
 * WebFrameworkPHP: A small and simple web framework built using PHP.
 *
 * @copyright   Copyright (c) 2021-present, Mauritz Nilsson <mail@mauritzonline.com>
 * @license     MIT, https://github.com/MauritzOnline/WebFrameworkPHP/blob/v0.2.0/LICENSE
 * @version     0.2.0
 */

// TODO: add more tests to the Python test script
// TODO: utilize "_send_error" more

class WebFramework {
  // TODO: add regex route mode?
  private array $_options = array(
    "routes_folder" => "routes", // which folder to search for auto-loading routes
    "views_folder" => "views", // which folder to search for views used by "render_view"
    "handle_php_errors" => true, // true = a default error handler will be provided (otherwise the default PHP one will be used)
    "use_json_error_handler" => false, // true = will use "send_json" rather than "send" for the provided error handler
    "debug_mode" => false, // true = will print additional information when errors occur
    "include_status_code_in_sent_json" => true, // true = will add ["status"] to all JSON output sent using "send_json"
    "use_error_log_in_error_handler" => true, // true = will by default send detailed errors to error_log()
    "always_use_helmet" => true // true = will run "helmet" for every response
  );

  private string $_script_file;
  private string $_full_request_uri;
  private string $_root_uri;
  private array $_routes = array();
  private object|null $_found404 = null;
  private bool $_custom404Loaded = false;
  private $_error_handler;
  private array $_middleware = array();

  public object $request; // current request data, this gets written by the constructor
  public object|null $route = null; // this gets overwritten by start()
  public bool $debug_mode = false; // this gets overwritten by the constructor

  public function __construct(array $options) {
    foreach($this->_options as $key => $value) {
      if(isset($options[$key])) {
        $new_value = $options[$key];
        $expected_type = gettype($value);
        $given_type = gettype($new_value);

        if($given_type === $expected_type) {
          $this->_options[$key] = (is_string($new_value) ? trim($new_value) : $new_value);
        }
      }
    }

    $this->debug_mode = $this->_options["debug_mode"];

    $this->_script_file = $_SERVER["SCRIPT_NAME"];
    $this->_root_uri = $this->_str_replace_once("/index.php", "", $this->_script_file);
    $this->_full_request_uri = $_SERVER["REQUEST_URI"];

    $this->request = (object) array(
      "method" => $_SERVER["REQUEST_METHOD"],
      "content_type" => (isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : ""),
      "uri" => rtrim($this->_str_replace_once($this->_root_uri, "", $this->_full_request_uri), "/"),
      "token" => null, // Only gets parsed if the parse_auth() method is called before start()
      "credentials" => null, // Only gets parsed if the parse_auth() method is called before start()
      "query" => array(),
      "params" => array(),
      "body" => array(),
      "files" => array()
    );

    if(isset($_GET) && !empty($_GET)) {
      $this->request->uri = explode("?", $this->request->uri)[0];
      $this->request->uri = rtrim($this->request->uri, "/");
    }
    $this->request->uri = ($this->request->uri === "" ? "/" : $this->request->uri);

    if($this->_options["handle_php_errors"] === true) {
      // Default error handler
      if($this->_options["use_json_error_handler"] === true) {
        $this->_error_handler = function($error_code, $error_message) {
          $this->send_json(array(
            "error" => $error_message . " (E" . $error_code . ")!",
            "error_code" => $error_code
          ), 500);
        };
      } else {
        $this->_error_handler = function($error_code, $error_message) {
          $this->send($error_message . " (E" . $error_code . ")!", 500);
        };
      }

      /* register_shutdown_function(function() {
        die("(die) shutdown");
      }); */

      set_exception_handler(function($e) {
        $error_message = join(" ", array(
          "Type: " . get_class($e) . ";",
          "Message: {" . $e->getMessage() . "};",
          "File: {" . $e->getFile() . "};",
          "Line: {" . $e->getLine() . "};"
        ));

        if($this->_options["use_error_log_in_error_handler"] === true) {
          error_log("WebFrameworkPHP ERROR >> " . $error_message);
        }

        if($this->debug_mode) {
          $this->_send_error(10000, $error_message);
        } else {
          $this->_send_error(10000);
        }
      });
      
      set_error_handler(function($level, $message, $file, $line) {
        $error_message = "";
        if($this->debug_mode) {
          $error_type = "Unknown";
          switch ($level) {
            case E_USER_ERROR:
              $error_type = "Error";
              break;
        
            case E_USER_WARNING:
              $error_type = "Warning";
              break;
        
            case E_USER_NOTICE:
              $error_type = "Notice";
              break;
            }

          $error_message = join(" ", array(
            "Type: " . $error_type . ";",
            "Message: {" . $message . "};",
            "File: {" . $file . "};",
            "Line: {" . $line . "};"
          ));
          $this->_send_error(10001, $error_message);
        } else {
          $this->_send_error(10001);
        }
      });
    } else {
      $this->_error_handler = function() {};
    }
  }

  // Used for debugging
  public function get_debug_info() {
    return [
      "script_file" => $this->_script_file,
      "root_uri" => $this->_root_uri,
      "full_request_uri" => $this->_full_request_uri,
      "route" => $this->route,
      "request" => $this->request,
    ];
  }

  // Redirect to provided URL
  public function redirect(string $redirect_uri, bool $permanent = false) {
    header("Location: " . $redirect_uri, true, $permanent ? 301 : 302);
    exit();
  }

  // Redirect to provided local route
  public function local_redirect(string $route_str, bool $permanent = false) {
    $clean_route_str = ltrim(ltrim(rtrim($route_str, "/"), "."), "/");
    $this->redirect($this->to_local_uri($clean_route_str), $permanent);
  }

  // Returns a path prefixed with the root URI
  public function to_local_uri(string $path) {
    return $this->_root_uri . "/" . ltrim($path, "/");
  }

  // Returns a path prefixed with the HTTP host and root URI
  public function to_public_uri(string $path, bool $always_https = false) {
    return $this->get_http_host($always_https) . "/" . ltrim($this->to_local_uri($path), "/");
  }

  // Returns the servers HTTP host with port number appended (if port isn't 80 nor 443) and prefixes http(s)
  public function get_http_host(bool $always_https = false) {
    $server_port = (isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : "80");
    $http_host = $_SERVER["SERVER_NAME"];

    if($server_port !== "80" && $server_port !== "443") {
      if(!str_ends_with($http_host, ":" . $server_port)) {
        $http_host .= ":" . $server_port;
      }
    }

    return ($always_https === true ? "https" : $_SERVER["REQUEST_SCHEME"]) . "://" . $http_host;
  }

  // Returns the current request URI prefixed with the HTTP host and root URI
  public function get_public_request_uri(bool $always_https = false) {
    return $this->to_public_uri($this->request->uri, $always_https);
  }

  // Adds a GET method route to be loaded, with tagging that HTML will be rendered
  public function render_html(string $route_str, callable $route_callback, $route_args = array(), $status_code = 200, string $method = "GET") {
    if(!in_array(strtoupper($method), array("ALL", "GET", "POST", "PUT", "PATCH", "DELETE"))) {
      $method = "GET";
    }
    $this->_add_route(strtoupper($method), $route_str, $route_callback, array_replace($route_args, array(
      "__route_options" => array(
        "is_html" => true,
        "html_status_code" => $status_code
      )
    )));
  }

  // Adds a GET method route to be loaded, with tagging that HTML will be rendered
  public function render_view(string $route_str, string $view_str, $route_args = array(), $status_code = 200, string $method = "GET") {
    $view_str = preg_replace("/[.]php$/i", "", trim($view_str));
    $view_file = rtrim($this->_options["views_folder"], "/") . "/" . trim($view_str, "/") . ".php";

    if(!in_array(strtoupper($method), array("ALL", "GET", "POST", "PUT", "PATCH", "DELETE"))) {
      $method = "GET";
    }

    if(is_file($view_file) && is_readable($view_file)) {
      $route_callback = function() use($view_file) { require_once($view_file); };
      $this->_add_route(strtoupper($method), $route_str, $route_callback, array_replace($route_args, array(
        "__route_options" => array(
          "is_html" => true,
          "html_status_code" => $status_code
        )
      )));
    } else {
      $this->_send_error(50000, 'render_view(): Given view file "' . $view_file . '" is either not a file or is not readable!');
    }
  }

  // Adds a route to be loaded for all methods
  public function all(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("ALL", $route_str, $route_callback, $route_args);
  }
  // Adds a GET method route to be loaded
  public function get(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("GET", $route_str, $route_callback, $route_args);
  }
  // Adds a POST method route to be loaded
  public function post(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("POST", $route_str, $route_callback, $route_args);
  }
  // Adds a PUT method route to be loaded
  public function put(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("PUT", $route_str, $route_callback, $route_args);
  }
  // Adds a PATCH method route to be loaded
  public function patch(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("PATCH", $route_str, $route_callback, $route_args);
  }
  // Adds a DELETE method route to be loaded
  public function delete(string $route_str, callable $route_callback, array $route_args = array()) {
    $this->_add_route("DELETE", $route_str, $route_callback, $route_args);
  }

  // Sends a response to the client
  public function send(string $data, int $status_code = 200, string $content_type = "text/plain") {
    if($status_code < 100 || $status_code > 599) {
      $this->_send_error(20001, 'send(): Given HTTP status code "' . $status_code . '" is not in valid range (100-599)!');
    }

    http_response_code($status_code);
    header("Content-Type: " . trim($content_type));
    echo $data;
    exit();
  }

  // Sends a JSON response to the client, reads status code from $status_code (with Content-Type: application/json)
  public function send_json(object|array $data, int $status_code = 200, bool|null $include_status_code = null) {
    $data = (object) $data;
    if($include_status_code === null) {
      $include_status_code = $this->_options["include_status_code_in_sent_json"];
    }

    $final_data = $data;
    if($include_status_code === true) {
      unset($final_data->status);
      // Used to make sure status code is at the start of object
      $final_data = (object) array_merge(array(
        "status" => $status_code
      ), (array) $final_data);
    }

    $encoded_data = json_encode($final_data);

    if($encoded_data !== false) {
      $this->send($encoded_data, $status_code, "application/json");
    } else {
      $this->_send_error(20000, 'send_json(): Failed to encode provided data!');
    }
  }

  // Sends a JSON response to the client, reads status code from $data["status"] (with Content-Type: application/json)
  public function send_json_body(object|array $data, bool|null $include_status_code = null) {
    $data = (object) $data;
    $has_status_code = isset($data->status);
    $status_code = 200;

    if($include_status_code === null) {
      $include_status_code = $this->_options["include_status_code_in_sent_json"];
    }

    if($has_status_code) {
      // Check if provided status code in the data is a valid number
      $status_code = intval($data->status);
      if($status_code === 0) {
        $this->_send_error(20002, 'send_json_body(): Failed to decode status code from $data!');
      }
    }

    $final_data = $data;
    unset($final_data->status);
    if($include_status_code === true) {
      // Used to make sure status code is at the start of object
      $final_data = (object) array_merge(array(
        "status" => $status_code
      ), (array) $final_data);
    }

    $encoded_data = json_encode($final_data);

    if($encoded_data !== false) {
      $this->send($encoded_data, $status_code, "application/json");
    } else {
      $this->_send_error(20000, 'send_json_body(): Failed to encode provided data!');
    }
  }

  // Send a file to the client ($content_type is required if "finfo" is not supported on the server)
  public function send_file(string $file_path, string|null $download_file_name = null, string|null $content_type = null, bool $stream = false) {
    if(!is_file($file_path)) {
      throw new Exception('Either no file could be found at the provided file path: "' . $file_path . '", or the provided path is not a file!', 1000);
    }
    if(!is_readable($file_path)) {
      throw new Exception('Provided file path "' . $file_path . '" is not readable!', 1001);
    }

    if($download_file_name === null || trim($download_file_name) === "") {
      // Get the file name from the file path
      $download_file_name = basename($file_path);
    }

    if($content_type === null || trim($content_type) === "") {
      if (extension_loaded("fileinfo")) {
        // Create a new file info object
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        // Get the MIME type of the file
        $mime_type = $finfo->file($file_path);
        // Set Content-Type of file to send (defaults to plain text if finfo fails)
        $content_type = ($mime_type !== false ? $mime_type : "text/plain");
      } else {
        throw new Exception('Failed to set Content-Type automatically! Extension fileinfo (finfo) does not appear to be loaded!', 2000);
      }
    }

    // Set the content length (filesize)
    $content_length = filesize($file_path);

    // Set the start and end bytes for the range
    $start_byte = 0;
    $end_byte = ($content_length === false ? 1 : $content_length) - 1;
    $content_length = ($content_length === false ? 1 : 0);

    // Set the HTTP headers
    http_response_code(200);
    header("Content-Disposition: attachment; filename=\"$download_file_name\"");
    header("Content-Type: $content_type");
    header("Content-Length: $content_length");

    if($stream === true) {
      header("Content-Range: bytes $start_byte-$end_byte/$content_length");
    }

    // Send the file
    $result = readfile($file_path);
    if($result === false) {
      throw new Exception('Failed to output given file: "' . $file_path . '"!', 3000);
    }
    exit();
  }

  // Move an uploaded file to the folder in $dest_folder
  public function move_uploaded_file(string $file, string $dest_folder = ".", array $options = array()): string {
    if(empty($this->request->files)) {
      throw new Exception("No files could be found in request!", 1000);
    }
  
    if(!isset($this->request->files[$file])) {
      throw new Exception('Could not find the uploaded file under the property "' . $file . '"!', 1001);
    }
    
    $file = $this->request->files[$file];
    if($file["error"] !== UPLOAD_ERR_OK) {
      throw new Exception("An error occurred with the upload (Error: " . $file["error"] . ")!", 1002);
    }

    if(!isset($options["new_file_name"]) || !is_string($options["new_file_name"])) {
      $options["new_file_name"] = "";
    }
    if(!isset($options["new_file_ext"]) || !is_string($options["new_file_ext"])) {
      $options["new_file_ext"] = "";
    }
    if(!isset($options["allowed_exts"]) || !is_array($options["allowed_exts"])) {
      $options["allowed_exts"] = array();
    }
    if(!isset($options["min_size"]) || !is_int($options["min_size"])) {
      $options["min_size"] = -1;
    }
    if(!isset($options["max_size"]) || !is_int($options["max_size"])) {
      $options["max_size"] = -1;
    }
    if(!isset($options["remove_invalid_files"]) || !is_bool($options["remove_invalid_files"])) {
      $options["remove_invalid_files"] = true;
    }

    // only allow non-empty strings
    $options["allowed_exts"] = array_filter($options["allowed_exts"], function($ext) {
      return is_string($ext) && trim(trim($ext, ".")) !== "";
    });

    // make extensions more consistent (removes prefix and suffix dots, makes extensions lowercase)
    $options["allowed_exts"] = array_map(function($ext) {
      return strtolower(trim(trim($ext, ".")));
    }, $options["allowed_exts"]);
  
    // Get the temporary file path
    $tmp_file = $file["tmp_name"];

    $file_name = trim($file["name"]);
    $file_ext = ltrim(pathinfo($file_name, PATHINFO_EXTENSION), ".");
    $file_name = trim(basename($file_name, "." . $file_ext));
    $file_size = filesize($tmp_file);

    if(count($options["allowed_exts"]) > 0) {
      if(!in_array(strtolower($file_ext), $options["allowed_exts"])) {
        if($options["remove_invalid_files"] === true) {
          $this->_delete_temp_uploaded_file($tmp_file);
        }
        throw new Exception("Uploaded file does not have an allowed extension!", 2000);
      }
    }

    if($options["min_size"] > 0) {
      if($file_size < $options["min_size"]) {
        if($options["remove_invalid_files"] === true) {
          $this->_delete_temp_uploaded_file($tmp_file);
        }
        throw new Exception("Uploaded file is too small!", 2001);
      }
    }

    if($options["max_size"] > 0) {
      if($file_size > $options["max_size"]) {
        if($options["remove_invalid_files"] === true) {
          $this->_delete_temp_uploaded_file($tmp_file);
        }
        throw new Exception("Uploaded file is too large!", 2002);
      }
    }

    $new_file_name = trim($options["new_file_name"]);
    if($new_file_name !== "") {
      $file_name = $new_file_name;
    }

    $new_file_ext = trim($options["new_file_ext"]);
    if($new_file_ext !== "") {
      $file_ext = ltrim($new_file_ext, ".");
    }
  
    // Generate a new file name
    $final_file_name = $file_name . "." . strtolower($file_ext);
    $final_file_name = str_replace("/", "", $final_file_name);

    $dest_folder = trim($dest_folder);
    $folder_path = ($dest_folder !== "" ? $dest_folder : ".");
    $folder_path = rtrim($folder_path, "/");

    if(!is_dir($folder_path)) {
      throw new Exception("Given destination is either not a folder or does not exist (" . $folder_path . ")!", 3000);
    }
    if(!is_writable($folder_path)) {
      throw new Exception("Could not write to given folder path (" . $folder_path . ")!", 3001);
    }
  
    // Set the target file path
    $target_file = $folder_path . "/" . $final_file_name;
  
    // Move the file from the temporary location to the target location
    if (move_uploaded_file($tmp_file, $target_file)) {
      return $target_file;
    } else {
      throw new Exception("Failed to move uploaded file!", 4000);
    }
  }


  /* Activates the following HelmetJS defaults [must be called before start()]:
      - contentSecurityPolicy
      - crossOriginEmbedderPolicy
      - crossOriginOpenerPolicy
      - crossOriginResourcePolicy
      - originAgentCluster
      - dnsPrefetchControl
      - expectCt
      - frameguard
      - hsts
      - ieNoOpen
      - noSniff
      - permittedCrossDomainPolicies
      - referrerPolicy
      - xssFilter
      - hidePoweredBy
  */
  public function helmet() {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; font-src 'self' https: data:; form-action 'self'; frame-ancestors 'self'; img-src 'self' data:; object-src 'none'; script-src 'self'; script-src-attr 'none'; style-src 'self' https: 'unsafe-inline'; upgrade-insecure-requests"); // contentSecurityPolicy

    header("Cross-Origin-Embedder-Policy: require-corp"); // crossOriginEmbedderPolicy
    header("Cross-Origin-Opener-Policy: same-origin"); // crossOriginOpenerPolicy
    header("Cross-Origin-Resource-Policy: same-origin"); // crossOriginResourcePolicy
    header("Origin-Agent-Cluster: ?1"); // originAgentCluster

    header("X-DNS-Prefetch-Control: off"); // dnsPrefetchControl
    header("Expect-CT: max-age=0"); // expectCt
    header("X-Frame-Options: SAMEORIGIN"); // frameguard
    header("Strict-Transport-Security: max-age=15552000; includeSubDomains"); // hsts
    header("X-Download-Options: noopen"); // ieNoOpen
    header("X-Content-Type-Options: nosniff"); // noSniff
    header("X-Permitted-Cross-Domain-Policies: none"); // permittedCrossDomainPolicies
    header("Referrer-Policy: no-referrer"); // referrerPolicy
    header("X-XSS-Protection: 0"); // xssFilter
    header_remove("X-Powered-By"); // hidePoweredBy
  }

  // Parse "Basic base64(username:password)" & "Bearer token" provided by HTTP requests and if valid adds it to $this->request->credentials & $this->request->token
  public function parse_auth() {
    $header = null;
    $this->request->token = null;
    $this->request->credentials = null;

    // Authorization header getting code from: https://stackoverflow.com/a/40582472
    if(isset($_SERVER["Authorization"])) {
      $header = trim($_SERVER["Authorization"]);
    } else if(isset($_SERVER["HTTP_AUTHORIZATION"])) { // Nginx or fast CGI
      $header = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } else if(function_exists("apache_request_headers")) {
      $request_headers = apache_request_headers();
      // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
      $request_headers = array_combine(array_map("ucwords", array_keys($request_headers)), array_values($request_headers));

      if(isset($request_headers["Authorization"])) {
        $header = trim($request_headers["Authorization"]);
      }
    }

    // strlen > 6, comes from "Basic X" & "Bearer X", since it has to be at least 7 characters to contain a token
    if($header !== null && strlen($header) > 6) {
      if(str_starts_with($header, "Bearer ")) {
        $exploded_header = explode(" ", $header);
        if(count($exploded_header) > 1) {
          $this->request->token = $exploded_header[array_key_last($exploded_header)];
        }
      } else if(str_starts_with($header, "Basic ")) {
        $exploded_header = explode(" ", $header);
        if(count($exploded_header) > 1) {
          $encoded_credentials = $exploded_header[array_key_last($exploded_header)];
          $decoded_credentials = base64_decode($encoded_credentials);

          // strlen > 2, comes from "Username:Password", since it has to be at least 3 characters to contain both a username and password
          if($decoded_credentials !== false && strlen($decoded_credentials) > 2) {
            // Use a regular expression to extract the username and password
            $match_result = preg_match('/^(?<username>.+?):(?<password>.+)$/', $decoded_credentials, $matches);
            if($match_result === 1) {
              // INFO: ":" inside the username is not supported as per the spec, but are fine in the password field.
              // INFO: ":" inside the username behaves like this: "john:doe:password" => Username: "john", Password: "doe:password"
              // Spec: https://www.rfc-editor.org/rfc/rfc2617#section-2
              
              $this->request->credentials = array(
                // INFO: Trims username & password, since "  john.doe" or "password  ", are not desired.
                "username" => trim($matches["username"]),
                "password" => trim($matches["password"])
              );
            }
          }
        }
      }
    }
  }

  public function set_custom_error_handler(callable $func) {
    $this->_error_handler = $func;
  }

  // Start the web framework (matching route, parsing data, etc...)
  public function start() {
    if($this->_options["always_use_helmet"] === true) $this->helmet();
    if(!empty($this->_options["routes_folder"])) $this->_load_routes();
    $this->route = null;
    $this->_found404 = null;
    $found_route = null;

    // Find matching route for current URI and parse URI params. Starts from the end of the array since we want to load in the last loaded route that matches and ignore any other matching routes and have been overwritten by the later loaded ones
    for($i = count($this->_routes) - 1; $i >= 0; $i--) {
      $route = $this->_routes[$i];
      
      if($route->method === $this->request->method || $route->method === "ALL") {
        if($this->_custom404Loaded === true && $route->uri === ":404" && $this->_found404 === null) {
          // Make sure the found 404 route has a callback
          if(property_exists($route, "callback")) {
            $this->_found404 = $route;
          }
        } else {
          if($found_route === null) {
            array_splice($this->request->params, 0); // reset any already parsed params
            $route_uri_sections = explode("/", $route->uri);
            $request_uri_sections = explode("/", $this->request->uri);
            $uri_matches = true;
            
            // Check if the request URI and the route URI has the same amount of sections
            if(count($route_uri_sections) === count($request_uri_sections)) {
              foreach ($request_uri_sections as $key => $value) {
                // Check if the current request URI section matches the current route URI section
                if($route_uri_sections[$key] !== $value) {
                  // Check if current route URI section is an URI param, if it is ignore the URI mismatch
                  if(str_starts_with($route_uri_sections[$key], ":")) {
                    // Parse the URI param
                    $this->request->params[ltrim($route_uri_sections[$key], ":")] = urldecode($value);
                  } else {
                    $uri_matches = false;
                  }
                }
              }
            } else {
              // The URI sections didn't match, so this is not the correct route for the request URI
              $uri_matches = false;
            }

            // Check if the request URI found a matching route that has a callback function attached to it
            if($uri_matches === true && property_exists($route, "callback")) {
              $found_route = $route;

              // Check if a custom 404 has been found, if it has then it's safe to exit the loop early, since everything has now been found
              if($this->_found404 !== null || $this->_custom404Loaded === false) {
                break;
              }
            }
          }
        }
      }
    }

    if($found_route !== null) {
      $this->request->query = (isset($_GET) && !empty($_GET) ? $_GET : array());

      // Handle "form-data" & "x-www-form-urlencoded" & parse raw[application/json] body (skips is HTTP method is "GET")
      if($this->request->method !== "GET") {
        if(isset($_FILES) && !empty($_FILES)) {
          $this->request->files = $_FILES;
        }

        if(isset($_POST) && !empty($_POST)) {
          $this->request->body = $_POST;
        } else if($this->request->content_type === "application/json") {
          try {
            $obj_body = json_decode(file_get_contents("php://input"), true);
            $this->request->body = (isset($obj_body) && !empty($obj_body) ? $obj_body : array());
          } catch(Exception $error) {
            error_log("WebFrameworkPHP WARNING >> Failed to decode JSON body in request!");
          }
        }
      }

      // Set current route & Run found route's callback
      $this->_set_current_route($found_route);
      $this->_run_middleware();
      $this->_run_route($found_route);
    } else {
      if($this->_found404 !== null) {
        $this->_set_current_route($this->_found404);
        $this->_run_middleware();
        $this->_run_route($this->_found404);
      } else {
        // No matching route could be found, send default 404
        $this->_set_current_route((object) array(
          "method" => $this->request->method,
          "uri" => ":404",
          "args" => array()
        ));
        $this->_run_middleware();
        $this->_not_found();
      }
    }
  }

  public function add_middleware(callable $middleware) {
    array_push($this->_middleware, $middleware);
  }

  private function _run_middleware() {
    foreach($this->_middleware as $key => $middleware) {
      call_user_func($middleware);
    }
  }

  private function _set_current_route(object $route) {
    if(is_object($route)) {
      $this->route = clone $route;
      if(!property_exists($this->route, "args")) {
        $this->route->args = array();
      }

      // remove unneeded properties
      unset($this->route->callback);
      unset($this->route->is_html);
      unset($this->route->html_status_code);
    }
  }

  private function _run_route(object $route) {
    if(is_callable($route->callback)) {
      if($route->is_html) {
        http_response_code($route->html_status_code);
        header("Content-Type: text/html");
      }

      call_user_func($route->callback);

      if($route->is_html) {
        exit();
      }
    }
  }

  // Sends default 404 response (should not be used directly)
  private function _not_found() {
    $this->send("Not found!", 404);
  }
  
  // Calls the defined error handler function
  private function _send_error(int $error_code = 11111, string $debug_error_message = "", string $error_message = "Internal server error") {
    if($this->debug_mode === true && strlen($debug_error_message) > 0) {
      $error_message = $debug_error_message;
    }

    call_user_func($this->_error_handler, $error_code, $error_message);
    error_log("WebFrameworkPHP ERROR >> An internal error occurred in WebFrameworkPHP (E" . $error_code . ")!");
  }

  // Adds route (should not be used directly, use get(), post(), etc...)
  private function _add_route(string $method, string $route_str, callable $route_callback, array $route_args = array()) {
    $clean_route_str = trim(explode("?", $route_str)[0]);

    if(str_starts_with($clean_route_str, ":404")) {
      $this->_custom404Loaded = true;
    }

    $route_is_html = false;
    $html_status_code = 200;

    if(isset($route_args["__route_options"])) {
      $route_options = array_slice($route_args["__route_options"], 0);
      
      if(isset($route_options["is_html"]) && is_bool($route_options["is_html"])) {
        $route_is_html = $route_options["is_html"];
      }
      if(isset($route_options["html_status_code"]) && is_int($route_options["html_status_code"])) {
        $html_status_code = $route_options["html_status_code"];
      }

      unset($route_args["__route_options"]);
    }

    array_push($this->_routes, (object) [
      "method" => $method,
      "uri" => $clean_route_str === "/" ? $clean_route_str : rtrim($clean_route_str, "/"),
      "callback" => $route_callback,
      "args" => $route_args,
      "is_html" => $route_is_html,
      "html_status_code" => $html_status_code,
    ]);
  }

  // Auto loads routes (should not be used directly, use start())
  private function _load_routes() {
    if(is_dir($this->_options["routes_folder"]) && is_readable($this->_options["routes_folder"])) {
      // Find all endpoints and require them (ignores hidden files)
      foreach(scandir($this->_options["routes_folder"]) as $key => $endpoint) {
        if(!str_starts_with($endpoint, ".")) {
          if(str_ends_with($endpoint, ".php") || str_ends_with($endpoint, ".PHP")) {
            require_once($this->_options["routes_folder"] . "/" . $endpoint);
          }
        }
      }
    } else {
      $this->_send_error(11000, 'Given routes folder "' . $this->_options["routes_folder"] . '" is either not a folder or is not readable!');
    }
  }

  // Replaces found string in provided string with something else but only replaces the first occurrence
  private function _str_replace_once(string $needle, string $replace, string $haystack) {
    $pos = strpos($haystack, $needle);
    if($pos !== false) {
      return substr_replace($haystack, $replace, $pos, strlen($needle));
    } else {
      return $haystack;
    }
  }

  // Deletes a temporary file (do NOT use this function directly, its only suppose to be used by `move_uploaded_file`)
  private function _delete_temp_uploaded_file(string $file_path) {
    if(is_file($file_path) && is_writable($file_path)) {
      if(!unlink($file_path)) {
        error_log('WebFrameworkPHP WARNING >> Failed to delete temp uploaded file: "' . $file_path . '"');
      }
    }
  }
}

?>
