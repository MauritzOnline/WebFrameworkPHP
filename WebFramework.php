<?php
/**
 * WebFrameworkPHP: A small and simple web framework built using PHP.
 *
 * @copyright   Copyright (c) 2021, Mauritz Nilsson <mail@mauritzonline.com>
 * @license     MIT, https://github.com/MauritzOnline/WebFrameworkPHP/blob/main/LICENSE
 * @version     0.0.3
 */

class WebFramework {
  private string $_routes_folder;
  private string $_script_file;
  private string $_full_request_uri;
  private bool $_route_has_params = false;
  private $_routes = array();

  protected string $root_uri;
  protected string $found_route_uri;

  protected $request;

  public function __construct($routes_folder = "routes") {
    $this->_routes_folder = $routes_folder;
    $this->_script_file = $_SERVER["SCRIPT_NAME"];
    $this->root_uri = str_replace("/index.php", "", $this->_script_file);
    $this->_full_request_uri = $_SERVER["REQUEST_URI"];

    $this->request = (object) array(
      "method" => $_SERVER["REQUEST_METHOD"],
      "content_type" => (isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : ""),
      "uri" => rtrim(str_replace($this->root_uri, "", $this->_full_request_uri), "/"),
      "query" => array(),
      "params" => array(),
      "body" => array(),
    );

    $this->found_route_uri = "";

    $this->request->uri = ($this->request->uri === "" ? "/" : $this->request->uri);
    if(isset($_GET) && !empty($_GET)) {
      $this->request->uri = explode("?", $this->request->uri)[0];
    }
  }

  // Used for debugging
  public function get_debug_info() {
    return [
      "script_file" => $this->_script_file,
      "root_uri" => $this->root_uri,
      "full_request_uri" => $this->_full_request_uri,
      "found_route_uri" => $this->found_route_uri,
      "request" => $this->request,
    ];
  }

  // Adds a GET method route to be loaded, with tagging that HTML will be rendered
  public function render_html(string $route_str, callable $route_callback, $status_code = 200) {
    $this->_add_route("GET", $route_str, $route_callback, true, $status_code);
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

    $this->send(json_encode($final_data), $final_data->status, "application/json");
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

  // Start the web framework (matching route, parsing data, etc...)
  public function start() {
    if(!empty($this->_routes_folder)) $this->_load_routes();
    $this->_route_has_params = false;

    // Find matching routes for current URI
    $matching_routes = array_filter($this->_routes, function($route) {
      if($route->method === $this->request->method) {
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

      // Parse URI params
      if($this->_route_has_params) {
        $exploded_route_uri = explode("/", $matching_routes[$arr_key]->uri);
        $exploded_request_uri = explode("/", $this->request->uri);
        
        if(count($exploded_route_uri) === count($exploded_request_uri)) {
          foreach ($exploded_request_uri as $key => $value) {
            if($exploded_route_uri[$key] !== $value) {
              if(str_starts_with($exploded_route_uri[$key], ":")) {
                $this->request->params[ltrim($exploded_route_uri[$key], ":")] = $value;
              }
            }
          }
        }
      }

      // Run found route's callback
      if(is_callable($matching_routes[$arr_key]->callback)) {
        if($matching_routes[$arr_key]->is_html) {
          http_response_code($matching_routes[$arr_key]->html_status_code);
          header("Content-Type: text/html");
        }

        $this->found_route_uri = $matching_routes[$arr_key]->uri;
        call_user_func($matching_routes[$arr_key]->callback);

        if($matching_routes[$arr_key]->is_html) {
          exit();
        }
      }
    } else {
      // No matching route could be found, send default 404
      $this->_not_found();
    }
  }

  // Sends default 404 response (should not be used directly)
  private function _not_found() {
    $this->send("Not found!", 404);
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
      if(preg_match("/^[.]/i", $endpoint, $matches) === 0) {
        if(preg_match("/[.]php$/i", $endpoint, $matches) === 1) {
          require_once($this->_routes_folder . "/" . $endpoint);
        }
      }
    }
  }
}

?>