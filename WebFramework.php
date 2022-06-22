<?php
/**
 * WebFrameworkPHP: A small and simple web framework built using PHP.
 *
 * @copyright   Copyright (c) 2021, Mauritz Nilsson <mail@mauritzonline.com>
 * @license     MIT, https://github.com/MauritzOnline/WebFrameworkPHP/blob/main/LICENSE
 * @version     0.0.5
 */

// TODO: create extensive testing project (will be tested using Postman, or a similar tool)
// TODO: utilize "_send_error" more

class WebFramework {
  private string $_routes_folder;
  private string $_script_file;
  private string $_full_request_uri;
  private string $_root_uri;
  private string $_found_route_uri;
  private bool $_route_has_params = false;
  private $_routes = array();
  private $_found404 = null;
  private $_error_handler;

  public $request; // current request data
  public $debug_mode = false; // true = will print additional information when errors occur
  public $use_error_log = true; // true = will by default send detailed errors to error_log()

  public function __construct($routes_folder = "routes", $provide_error_handler = true) {
    $this->_routes_folder = $routes_folder;
    $this->_script_file = $_SERVER["SCRIPT_NAME"];
    $this->_root_uri = $this->_str_replace_once("/index.php", "", $this->_script_file);
    $this->_full_request_uri = $_SERVER["REQUEST_URI"];

    $this->request = (object) array(
      "method" => $_SERVER["REQUEST_METHOD"],
      "content_type" => (isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : ""),
      "uri" => rtrim($this->_str_replace_once($this->_root_uri, "", $this->_full_request_uri), "/"),
      "token" => null, // Only gets parsed if the auth() method is called before start()
      "query" => array(),
      "params" => array(),
      "body" => array(),
    );

    $this->_found_route_uri = "";

    if(isset($_GET) && !empty($_GET)) {
      $this->request->uri = explode("?", $this->request->uri)[0];
      $this->request->uri = rtrim($this->request->uri, "/");
    }
    $this->request->uri = ($this->request->uri === "" ? "/" : $this->request->uri);

    if($provide_error_handler) {
      // Default error handler
      $this->_error_handler = function($error_code, $error_message) {
        $this->send($error_message . " (E" . $error_code . ")!", 500);
      };

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

        if($this->use_error_log === true) {
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
      "found_route_uri" => $this->_found_route_uri,
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
    return $this->root_uri . "/" . ltrim($path, "/");
  }

  // Returns a path prefixed with the HTTP host and root URI
  public function to_public_uri(string $path, bool $always_https = false) {
    $server_port = (isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : "80");
    $http_host = $_SERVER["SERVER_NAME"];

    if($server_port !== "80" && $server_port !== "443") {
      if(!str_ends_with($http_host, ":" . $server_port)) {
        $http_host .= ":" . $server_port;
      }
    }

    return ($always_https === true ? "https" : $_SERVER["REQUEST_SCHEME"]) . "://" . $http_host . "/" . ltrim($this->to_local_uri($path), "/");
  }

  // Returns the current request URI prefixed with the HTTP host and root URI
  public function get_public_request_uri(bool $always_https = false) {
    return $this->to_public_uri($this->request->uri, $always_https);
  }

  // Adds a GET method route to be loaded, with tagging that HTML will be rendered
  public function render_html(string $route_str, callable $route_callback, $status_code = 200, string $method = "GET") {
    if(!in_array(strtoupper($method), array("ALL", "GET", "POST", "PUT", "PATCH", "DELETE"))) {
      $method = "GET";
    }
    $this->_add_route(strtoupper($method), $route_str, $route_callback, true, $status_code);
  }

  // Adds a route to be loaded for all methods
  public function all(string $route_str, callable $route_callback) {
    $this->_add_route("ALL", $route_str, $route_callback);
  }
  // Adds a GET method route to be loaded
  public function get(string $route_str, callable $route_callback) {
    $this->_add_route("GET", $route_str, $route_callback);
  }
  // Adds a POST method route to be loaded
  public function post(string $route_str, callable $route_callback) {
    $this->_add_route("POST", $route_str, $route_callback);
  }
  // Adds a PUT method route to be loaded
  public function put(string $route_str, callable $route_callback) {
    $this->_add_route("PUT", $route_str, $route_callback);
  }
  // Adds a PATCH method route to be loaded
  public function patch(string $route_str, callable $route_callback) {
    $this->_add_route("PATCH", $route_str, $route_callback);
  }
  // Adds a DELETE method route to be loaded
  public function delete(string $route_str, callable $route_callback) {
    $this->_add_route("DELETE", $route_str, $route_callback);
  }

  // Sends a response to the client
  public function send(string $data, int $status_code = 200, string $content_type = "text/plain") {
    http_response_code($status_code);
    header("Content-Type: " . trim($content_type));
    echo $data;
    exit();
  }

  // Sends a JSON response to the client (with Content-Type: application/json)
  public function send_json(object|array $data) {
    $data = (object) $data;
    if(!isset($data->status) || !is_numeric($data->status)) {
      $data->status = 200;
    }
    if(is_string($data->status)) {
      $data->status = intval($data->status);
    }

    // Used to make sure status code is at the start of object
    $final_data = (object) array_merge(array(
      "status" => $data->status
    ), (array) $data);

    $encoded_data = json_encode($final_data);

    if($encoded_data !== false) {
      $this->send($encoded_data, $final_data->status, "application/json");
    } else {
      $this->_send_error(20000);
    }
  }

  /* Activates the following HelmetJS defaults [must be called before start()]:
      - contentSecurityPolicy
      - dnsPrefetchControl
      - expectCt
      - frameguard
      - hidePoweredBy
      - hsts
      - ieNoOpen
      - noSniff
      - permittedCrossDomainPolicies
      - referrerPolicy
      - xssFilter
  */
  public function helmet() {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; block-all-mixed-content; font-src 'self' https: data:; frame-ancestors 'self'; img-src 'self' data:; object-src 'none'; script-src 'self'; script-src-attr 'none'; style-src 'self' https: 'unsafe-inline'; upgrade-insecure-requests");
    header("X-DNS-Prefetch-Control: off");
    header("Expect-CT: max-age=0");
    header("X-Frame-Options: SAMEORIGIN");
    header("Strict-Transport-Security: max-age=15552000; includeSubDomains");
    header("X-Download-Options: noopen");
    header("X-Content-Type-Options: nosniff");
    header("X-Permitted-Cross-Domain-Policies: none");
    header("Referrer-Policy: no-referrer");
    header("X-XSS-Protection: 0");
    header_remove("X-Powered-By");
  }

  // Parse Bearer token provided by HTTP requests and if valid adds it to $this->request->token
  public function parse_auth() {
    $header = null;
    $this->request->token = null;

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

    // strlen > 7, comes from "Bearer X", since it has to be at least 8 characters to contain a token
    if($header !== null && strlen($header) > 7) {
      if(str_starts_with($header, "Bearer ")) {
        $exploded_header = explode(" ", $header);
        if(count($exploded_header) > 1) {
          $this->request->token = $exploded_header[array_key_last($exploded_header)];
        }
      }
    }
  }

  public function set_custom_error_handler(callable $func) {
    $this->_error_handler = $func;
  }

  // Start the web framework (matching route, parsing data, etc...)
  public function start() {
    if(!empty($this->_routes_folder)) $this->_load_routes();
    $this->_route_has_params = false;
    $this->_found404 = null;

    // Find matching routes for current URI, TODO: optimize
    $matching_routes = array_filter($this->_routes, function($route) {
      if($route->method === $this->request->method || $route->method === "ALL") {
        if($route->uri === ":404") {
          $this->_found404 = $route;
        } else {
          $exploded_route_uri = explode("/", $route->uri);
          $exploded_request_uri = explode("/", $this->request->uri);
          $uri_matches = true;
          
          if(count($exploded_route_uri) === count($exploded_request_uri)) {
            foreach ($exploded_request_uri as $key => $value) {
              if($exploded_route_uri[$key] !== $value) {
                if(!str_starts_with($exploded_route_uri[$key], ":")) {
                  $uri_matches = false;
                } else {
                  $this->_route_has_params = true;
                }
              }
            }
          } else {
            $uri_matches = false;
          }

          return $uri_matches;
        }
      } else {
        return false;
      }
    });

    $route_exists = false;
    $arr_key = 0;

    // Check if at least one route was found with a callback
    if(count($matching_routes) > 0) {
      $arr_key = array_key_last($matching_routes);
      if(property_exists($matching_routes[$arr_key], "callback")) {
        $route_exists = true;
      }
    }

    if($this->_found404 !== null && !property_exists($this->_found404, "callback")) {
      $this->_found404 = null;
    }

    if($route_exists) {
      $this->request->query = (isset($_GET) && !empty($_GET) ? $_GET : array());

      // Handle "form-data" & "x-www-form-urlencoded" & parse raw[application/json] body (skips is HTTP method is "GET")
      if($this->request->method !== "GET") {
        if(isset($_POST) && !empty($_POST)) {
          $this->request->body = $_POST;
        } else if($this->request->content_type === "application/json") {
          try {
            $obj_body = json_decode(file_get_contents("php://input"), true);
            $this->request->body = (isset($obj_body) && !empty($obj_body) ? $obj_body : array());
          } catch(Exception $error) {
            error_log("Failed to decode JSON body in request!");
          }
        }
      }

      // Parse URI params, TODO: try to get this included in the URI matcher?
      if($this->_route_has_params) {
        $exploded_route_uri = explode("/", $matching_routes[$arr_key]->uri);
        $exploded_request_uri = explode("/", $this->request->uri);
        
        if(count($exploded_route_uri) === count($exploded_request_uri)) {
          foreach ($exploded_request_uri as $key => $value) {
            if($exploded_route_uri[$key] !== $value) {
              if(str_starts_with($exploded_route_uri[$key], ":")) {
                $this->request->params[ltrim($exploded_route_uri[$key], ":")] = urldecode($value);
              }
            }
          }
        }
      }

      // Run found route's callback
      $this->_run_route($matching_routes[$arr_key]);
    } else {
      if($this->_found404 !== null) {
        $this->_run_route($this->_found404);
      } else {
        // No matching route could be found, send default 404
        $this->_not_found();
      }
    }
  }

  private function _run_route(object $route) {
    if(is_callable($route->callback)) {
      if($route->is_html) {
        http_response_code($route->html_status_code);
        header("Content-Type: text/html");
      }

      $this->_found_route_uri = $route->uri;
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
  private function _send_error(int $error_code = 11111, string $error_message = "Internal server error") {
    call_user_func($this->_error_handler, $error_code, $error_message);
    error_log("An internal error occurred in WebFrameworkPHP (E" . $error_code . ")!");
  }

  // Adds route (should not be used directly, use get(), post(), etc...)
  private function _add_route(string $method, string $route_str, callable $route_callback, bool $route_is_html = false, int $html_status_code = 200) {
    $clean_route_str = trim(explode("?", $route_str)[0]);
    array_push($this->_routes, (object) [
      "method" => $method,
      "uri" => $clean_route_str === "/" ? $clean_route_str : rtrim($clean_route_str, "/"),
      "callback" => $route_callback,
      "is_html" => $route_is_html,
      "html_status_code" => $html_status_code,
    ]);
  }

  // Auto loads routes (should not be used directly, use start())
  private function _load_routes() {
    // Find all endpoints and require them (ignores hidden files)
    foreach(scandir($this->_routes_folder) as $key => $endpoint) {
      if(!str_starts_with($endpoint, ".")) {
        if(str_ends_with($endpoint, ".php") || str_ends_with($endpoint, ".PHP")) {
          require_once($this->_routes_folder . "/" . $endpoint);
        }
      }
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
}

?>
