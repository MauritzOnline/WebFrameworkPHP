<?php

class WebFramework {
  private string $_routes_folder;
  private string $_script_file;
  private string $_full_request_uri;
  private bool $_route_has_params = false;
  private $_routes = array();

  protected string $root_uri;
  protected string $request_method;
  protected string $request_content_type;
  protected string $request_uri;
  protected string $found_route_uri;

  protected $request_query = array();
  protected $request_params = array();
  protected $request_body = array();

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

  public function get_debug_info() {
    return [
      "script_file" => $this->_script_file,
      "root_uri" => $this->root_uri,
      "full_request_uri" => $this->_full_request_uri,
      "found_route_uri" => $this->found_route_uri,
      "request" => $this->request,
    ];
  }

  public function get(string $route_str, callable $route_callback) {
    $this->_add_route("GET", $route_str, $route_callback);
  }
  public function post(string $route_str, callable $route_callback) {
    $this->_add_route("POST", $route_str, $route_callback);
  }
  public function put(string $route_str, callable $route_callback) {
    $this->_add_route("PUT", $route_str, $route_callback);
  }
  public function patch(string $route_str, callable $route_callback) {
    $this->_add_route("PATCH", $route_str, $route_callback);
  }
  public function delete(string $route_str, callable $route_callback) {
    $this->_add_route("DELETE", $route_str, $route_callback);
  }

  public function send(string $data, int $status_code = 200, string $content_type = "text/plain") {
    http_response_code($status_code);
    header("Content-Type: " . trim($content_type));
    echo $data;
    exit();
  }

  public function send_json(object|array $data) {
    $data = (object) $data;
    if(!isset($data->status) || !is_numeric($data->status)) {
      $data->status = 200;
    }
    if(is_string($data->status)) {
      $data->status = intval($data->status);
    }

    // used to make sure status code is at the start of object
    $final_data = (object) array_merge(array(
      "status" => $data->status
    ), (array) $data);

    $this->send(json_encode($final_data), $final_data->status, "application/json");
  }

  public function start() {
    if(!empty($this->_routes_folder)) $this->_load_routes();
    $this->_route_has_params = false;

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

    if(count($matching_routes) > 0) {
      $arr_key = array_key_last($matching_routes);
      if(property_exists($matching_routes[$arr_key], "callback")) {
        $route_exists = true;
      }
    }

    if($route_exists) {
      $this->request->query = (isset($_GET) && !empty($_GET) ? $_GET : array());

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

      if(is_callable($matching_routes[$arr_key]->callback)) {
        $this->found_route_uri = $matching_routes[$arr_key]->uri;
        call_user_func($matching_routes[$arr_key]->callback);
      }
    } else {
      $this->_not_found();
    }
  }

  private function _not_found() {
    $this->send("Not found!", 404);
  }

  private function _add_route(string $method, string $route_str, callable $route_callback) {
    array_push($this->_routes, (object) [
      "method" => $method,
      "uri" => explode("?", $route_str)[0],
      "callback" => $route_callback
    ]);
  }

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