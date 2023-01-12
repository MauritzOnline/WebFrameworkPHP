# WebFrameworkPHP

A small and simple web framework built using PHP. Handles routing and different request methods.

> This projects was made so that I would have a way of creating simple REST APIs on my server, since most of the existing PHP projects were either way more complex than I liked or required Composer or some other installation requirement. Size was another concern, I didn't want to have to deal with the following things for every small REST API on my server: `a bunch of small files littering my server`, `large packages`, `a bunch of package dependencies to keep track of`.

> While this is intended to be a small framework with close to native performance, I will still consider feature requests, but keep in-mind that I might reject your request if it doesn't align with the ethos of this project.

## TODO for `v1.0.0` release

- Route-based middlewares? _(unsure about this one since it would add quite a bit of complexity and can still be handled using a global middleware)_
- Add more comments
- Add more tests
- Add example projects
- Further expand documentation?

---

<!-- START ToC | Documentation -->
<!-- DON'T edit this section, instead run "generate_toc.py" to update -->

## Table of Contents | `Documentation`

- [Installation](#installation)
- [Error codes](#error-codes)
- [Constructor options](#constructor-options)
  - [Routes folder](#routes-folder)
  - [Views folder](#views-folder)
  - [Provide error handler](#provide-error-handler)
  - [Use JSON error handler](#use-json-error-handler)
  - [Debug mode](#debug-mode)
  - [Include status code in JSON output](#include-status-code-in-json-output)
  - [Use `error_log`](#use-error_log)
- [Request data](#request-data)
- [Route loading](#route-loading)
  - [Auto loading](#auto-loading)
  - [Manual loading](#manual-loading)
- [Routing](#routing)
  - [Route arguments](#route-arguments)
- [Global middleware](#global-middleware)
- [Redirection](#redirection)
  - [redirect()](#redirect)
  - [local_redirect()](#local_redirect)
- [HTTP host and URI utilities](#http-host-and-uri-utilities)
  - [to_local_uri()](#to_local_uri)
  - [to_public_uri()](#to_public_uri)
  - [get_http_host()](#get_http_host)
  - [get_public_request_uri()](#get_public_request_uri)
- [Sending responses](#sending-responses)
  - [send()](#send)
  - [send_json()](#send_json)
  - [send_json_body()](#send_json_body)
  - [send_file()](#send_file)
- [Moving uploaded files](#moving-uploaded-files)
  - [Exceptions](#exceptions)
- [HTML rendering](#html-rendering)
- [View rendering](#view-rendering)
- [Authentication](#authentication)
  - [Bearer Token](#bearer-token)
  - [Basic Authentication](#basic-authentication)
- [Custom 404 response](#custom-404-response)
- [Custom error handler](#custom-error-handler)
- [Custom headers](#custom-headers)
- [Helmet](#helmet)
- [CORS](#cors)

<!-- END ToC -->

---

## Installation

Go download version [0.2.0](https://github.com/MauritzOnline/WebFrameworkPHP/releases/tag/v0.2.0) _(latest release)_.

> The `main` branch can also be downloaded, but may include code that hasn't been properly tested yet.

Add `WebFramework.php` to your project and require it in `index.php`. To allow for routing a `.htaccess` file is used for Apache and `nginx.conf` for Nginx _(or `sites-available/DOMAIN`)_.

> This framework requires **PHP 8.0+** to work, this is due to the usage of the `object` type hint and the use of `str_starts_with` and `str_ends_with`.

`/index.php`

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework(); // default with auto loading from "routes" folder

// ... optional manual loading of additional routes ...

$webFramework->start(); // runs last (after all routes have been loaded)

?>
```

**Option 1: `.htaccess` when running framework at domain root _[`https://example.com/`]_**

```apacheconf
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# if forcing of https is wanted (excluding localhost)
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:[0-9]+)?$
RewriteRule ^.*$ https://%{HTTP_HOST}%{REQUEST_URI} [R,L]
```

**Option 2: `.htaccess` when running framework inside a folder _[`https://example.com/my_api/`]_**

```apacheconf
RewriteEngine On
RewriteBase /my_api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# if forcing of https is wanted (excluding localhost)
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1)(:[0-9]+)?$
RewriteRule ^.*$ https://%{HTTP_HOST}%{REQUEST_URI} [R,L]
```

**Option 3: `nginx.conf` when running framework at domain root _[`https://example.com/`]_**

> Since forcing **SSL** can be handled independently of this addition it won't be included in this example.

```apacheconf
server {
    # ... other config items ...

    rewrite ^/(.*)$ /index.php last;
}
```

**Option 4: `nginx.conf` when running framework inside a folder _[`https://example.com/my_api/`]_**

> Since forcing **SSL** can be handled independently of this addition it won't be included in this example.

```apacheconf
server {
    # ... other config items ...

    rewrite ^/my_api/(.*)$ /my_api/index.php last;
}
```

---

## Error codes

You can easily customize the default error handler that is provided. This error handler will deal with fatal errors and exceptions.

- **E10000:** Fatal error caused by loaded routes or other custom code.
- **E10001:** Error sent using `trigger_error`.
- **E20000:** Error sent from `send_json` or `send_json_body`, caused by failed JSON encode.
- **E20001:** Error sent from `send`, caused by an invalid HTTP status code _(code must be: 100-599)_.
- **E20002:** Error sent from `send_json_body`, caused by an invalid `status` in `$data` body _(must a number: 100-599)_.
- **E20100:** Error sent from `send_file`, caused by missing or unreadable file at the given `$file_path`.
- **E50000:** Error sent from `render_view`, caused by missing or unreadable file at the given `$view_str`.

---

## Constructor options

**Default values:**

```php
array(
  "routes_folder" => "routes",
  "views_folder" => "views",
  "provide_error_handler" => true,
  "use_json_error_handler" => false,
  "debug_mode" => false,
  "include_status_code_in_json" => true,
  "use_error_log" => true
);
```

**Example:**

```php
$webFramework = new WebFramework(array(
  "debug_mode" => true,
  "include_status_code_in_json" => false
));
```

### Routes folder

Will change the folder used for auto-loading routes.

### Views folder

Will change the folder that `render_view()` uses to find view files.

### Provide error handler

Wether WebFrameworkPHP should provide a default error handler function, if none is provided then the default PHP error handling is used.

### Use JSON error handler

Wether the default error handler function provided by WebFrameworkPHP should output the error as JSON.

**Example:**

```json
{
  "status": 500,
  "error": "...",
  "error_code": 10000
}
```

### Debug mode

Wether the default error handler function provided by WebFrameworkPHP should provide detailed errors. This can make debugging much easier, but can also include details that you don't want users to see, as such this should not be turned on in a production deployment.

Can also be activated using `$webFramework->debug_mode = true;` _(must be run before `start()`)_.

This option can also be used inside routes to provide more information while running in debug mode.

### Include status code in JSON output

Wether `send_json` and `send_json_body` should include the `"status": 200` in the response body. This toggles it globally, it can always be toggled on a per call basis by passing appropriate option to `send_json` or `send_json_body`.

### Use `error_log`

Wether the default error handler function provided by WebFrameworkPHP should also use `error_log` to log more detailed errors. This can be useful if you don't want to turn on debug mode, but still want to see the more detailed errors.

---

## Request data

> `request->uri` will always exclude the current directory, if you are running the framework from the domain root then this will not affect you. However if you are running from inside a folder, the URI will never include the folder, e.g. `/my_api/hello` becomes `/hello`.

**Structure of request data:**

```php
$this->request = (object) array(
  "method" => $_SERVER["REQUEST_METHOD"], // HTTP method of the request
  "content_type" => $_SERVER["CONTENT_TYPE"], // Content-Type of the request
  "uri" => "...", // the current URI
  "credentials" => array(...), // the parsed basic authentication credentials of the request (only gets parsed if the parse_auth() method is called before start()) [will be null if not found]
  "token" => "...", // the parsed bearer token of the request (only gets parsed if the parse_auth() method is called before start()) [will be null if not found]
  "query" => array(...), // parsed URI queries (?hello=world&abc=123)
  "params" => array(...), // parsed URI params (/:hello/:abc)
  "body" => array(...), // parsed post data (form-data, x-www-form-urlencoded, raw[application/json]) (will not be parsed if HTTP method is "GET")
  "files" => array(...), // parsed files data (will not be parsed if HTTP method is "GET")
);
```

**Examples:**

```bash
curl -X POST 'https://example.com/api/note/12345678/?type=sticky'\
     -H 'Authorization: Bearer my_secret_token'\
     -H "Content-type: application/json"\
     -d '{ "title": "My sticky note", "contents": "Remember Sunday" }'
```

```php
$this->post("/note/:id", function() {
  $this->request->token; // "my_secret_token" (can be null, either if Bearer token parsing wasn't enabled using parse_auth() or if a valid HTTP header couldn't be found in the request)
  $this->request->params["id"]; // "12345678" (required)
  $this->request->query["type"]; // "sticky" (can be missing, using isset() before accessing is recommended)
  $this->request->body["title"]; // "My sticky note" (can be missing, using isset() before accessing is recommended)
  $this->request->body["contents"]; // "Remember Sunday" (can be missing, using isset() before accessing is recommended)
  $this->send("Note added");
}
```

---

## Route loading

> All routing is done by placing PHP files inside either the default `routes` folder, or by choosing your own folder in the constructor. Auto loading of routes can be disabled by passing an empty string to the constructor _(e.g. `""`)_. Auto loading can used together with manual loading of additional routes.

### Auto loading

`/index.php`

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->start();

?>
```

`/routes/demo.php`

```php
<?php

$this->get("/demo", function() {
  $this->send_json_body(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

// ... additional paths ...

?>
```

### Manual loading

`/index.php`

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework(""); // "" => disables auto loading
require_once("./manual_route.php");
$webFramework->start();

?>
```

`/manual_route.php`

```php
<?php

$webFramework->get("/manual", function() use($webFramework) {
  $webFramework->send_json_body(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

// ... additional paths ...

?>
```

---

## Routing

> The following HTTP methods are available: `ALL` _(special)_, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`. URI queries in the route URI will be ignored, e.g. `"/document?hello=world"` will resolve to `/document`, as such URI queries should not be used in the route URI. The `all()` method can be used to load a route for all HTTP methods.

```php
<?php

// example #1: special "ALL" request handling (will handle GET, POST, PUT, etc.)
$this->all("/info", function() {
  $this->send_json_body(array(
    "status" => 200,
    "message" => "...",
  ));
});

// example #2: GET request handling
$this->get("/document", function() {
  // ... documents ...

  $this->send_json_body(array(
    "status" => 200,
    "message" => "Fetched all documents!",
    "documents" => array [...],
  ));
});

// example #3: GET request handling, with URI param
$this->get("/document/:id", function() {
  $document_id = $this->request->params["id"];

  // ... fetch document ...

  $this->send_json_body(array(
    "status" => 200,
    "message" => "Fetched single document!",
    "document" => object {...},
  ));
});

// example #4: POST request handling
$this->post("/document", function() {
  $document_title = $this->request->body["title"];
  $document_contents = $this->request->body["contents"];

  // ... create document ...

  $this->send_json_body(array(
    "status" => 200,
    "message" => "New document added!",
    "id" => $document_id
  ));
});

?>
```

### Route arguments

> Route arguments can be added to easily pass data that needs to be used by a middleware or code running inside a rendered view.

> One example use case for this is passing `"auth" => true` as an argument, then check in a middleware if the defined route requires authentication and only check for an auth token if that is provided. This solves the issue of having to check for authentication on every route or having to check the URI to know if it should have authentication or not.

```php
<?php

// example #1: special "ALL" request handling (will handle GET, POST, PUT, etc.)
$this->all("/info", function() {
  $this->send_json_body(array(
    "status" => 200,
    "message" => "...",
    "route_arg" => (isset($this->route->args["my_arg"]) ? $this->route->args["my_arg"] : "")
  ));
}, array("my_arg" => "my_important_value"));

// example #2: GET request handling
$this->get("/document", function() {
  // ... documents ...

  $this->send_json_body(array(
    "status" => 200,
    "message" => "Fetched all documents!",
    "documents" => array [...],
    "route_arg" => (isset($this->route->args["my_arg"]) ? $this->route->args["my_arg"] : "")
  ));
}, array("my_arg" => "my_important_value"));

// example #3: render view handling (see below example of document view, labeled `/views/document.php`)
$this->render_view("/document/:id", "document", array("my_arg" => "my_important_value"));

?>
```

`/views/document.php`

```php
<?php
  // ... logic to fetch document ...
?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document viewer</title>
  </head>

  <body>
    <h1><?php echo $document->title; ?></h1>
    <main>
      <p><?php echo (isset($this->route->args["my_arg"]) ? $this->route->args["my_arg"] : ""); ?></p>
      <p><?php echo $document->contents; ?></p>
    </main>
  </body>
</html>
```

---

## Global middleware

> Allows to do check before any route runs, this can be useful for authentication checks, using any of the `send` methods will stop execution of any routes. Route specific middleware might be supported in the future, but a similar function can already be accomplished by checking the value of `$webFramework->route->uri`.

> A global middleware is added by calling the `add_middleware()` method. Multiple middleware can be added, but they must all be added before the call to `start()`.

**Example:**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->parse_auth(); // this can either be placed here or inside the middleware (just make sure do not add it in two places) [recommended]

$webFramework->add_middleware(function() use($webFramework) {
  $route_args = $webFramework->route->args;
  $is_auth_required = (isset($route_args["auth"]) ? $route_args["auth"] : false);

  if($is_auth_required === true) {
    $webFramework->parse_auth(); // this can either be placed here or anytime before the adding of this middleware (just make sure do not add it in two places)

    if($webFramework->request->token === null) {
      $webFramework->send("Missing valid auth token!", 403); // stops any route from being run
    }
    if($webFramework->request->token !== "my_valid_secret_token") {
      $webFramework->send("Invalid auth token!", 403); // stops any route from being run
    }
  }
});

$webFramework->start();

?>
```

---

## Redirection

### redirect()

> Redirection function, allows you to redirect the user to a different URL. The status code is set to 301 for permanent redirects and 302 for temporary redirects.

```php
function redirect(string $redirect_uri, bool $permanent = false)
```

**Examples:**

```php
$this->redirect("https://example.com");

$this->redirect("https://example.com", true);
```

### local_redirect()

> Redirection function, allows you to redirect the user to a local route. The status code is set to 301 for permanent redirects and 302 for temporary redirects. Local redirection cannot escape from the root URI. As such if the framework isn't running from the root of the domain then the redirection will always be prefixed with the folder it's running inside.

```php
function local_redirect(string $route_str, bool $permanent = false)
```

**Examples:**

```php
$this->local_redirect("/my/local/route"); // "https://example.com/my/local/route"

// If the framework isn't running from the root of the domain then the folder will be included in the end result
$this->local_redirect("/my/local/route"); // "https://example.com/my_api/my/local/route"
```

---

## HTTP host and URI utilities

### to_local_uri()

> Path conversion function, returns a path prefixed with the root URI.

```php
function to_local_uri(string $path)
```

**Examples:**

```php
$this->to_local_uri("/my/local/route"); // "/my/local/route"

$this->to_local_uri("my/local/route"); // "/my/local/route"


// If the framework isn't running from the root of the domain then the folder will be included in the end result
$this->to_local_uri("/my/local/route"); // "/my_api/my/local/route"

$this->to_local_uri("my/local/route"); // "/my_api/my/local/route"
```

### to_public_uri()

> Path conversion function, returns a path prefixed with the HTTP host and root URI.

```php
function to_public_uri(string $path, bool $always_https = false)
```

**Examples:**

```php
$this->to_public_uri("/my/local/route"); // "https://example.com/my/local/route"

$this->to_public_uri("my/local/route"); // "https://example.com/my/local/route"


// If the framework isn't running from the root of the domain then the folder will be included in the end result
$this->to_public_uri("/my/local/route"); // "https://example.com/my_api/my/local/route"

$this->to_public_uri("my/local/route"); // "https://example.com/my_api/my/local/route"
```

### get_http_host()

> HTTP host function, returns the server's HTTP host with port number appended _(if the port is not 80 or 443)_ and prefixes http or https.

```php
function get_http_host(bool $always_https = false)
```

**Examples:**

```php
$this->get_http_host(); // "http://example.com" or "https://example.com" depending on the current request scheme

$this->get_http_host(true); // always "https://example.com"
```

### get_public_request_uri()

> Request URI function, returns the current request URI prefixed with the HTTP host and root URI

```php
function get_public_request_uri(bool $always_https = false)
```

**Examples:**

```php
$this->get_public_request_uri(); // "http://example.com/my/request/uri" or "https://example.com/my/request/uri" depending on the current request scheme

$this->get_public_request_uri(true); // always "https://example.com/my/request/uri"
```

---

## Sending responses

> Responses can either be sent using `send(...)`, `send_json(...)`, `send_json_body(...)` or `send_file(...)`. Responses exit PHP, so after they are sent no other code will be run.

### send()

> Base response function, allows you to send any data you want with any Content-Type and status code. HTTP status code must be a number _(100-599)_.

```php
function send(string $data, int $status_code = 200, string $content_type = "text/plain")
```

**Examples:**

```php
$this->send("Hello world!"); // 200 OK
$this->send("Hello world!", 400); // 400 Bad Request
$this->send("Hello world!", 500); // 500 Internal Error
$this->send(json_encode(array(...)), 200, "application/json"); // 200 OK
```

### send_json()

> JSON response function, allows you to send data as JSON. Response will always have the Content-Type of `application/json`. Status code is chosen by setting `$status_code`. `$data` can either be an associative array or an object. If `status` is omitted then it will default to `200`. HTTP status code must be a number _(100-599)_.

```php
function send_json(object|array $data, int $status_code = 200, bool $include_status_code = true)
```

**Examples:**

```php
$this->send_json(array(
  "hello" => "world!",
)); // 200 OK

$this->send_json(array(
  "hello" => "world!",
), 200); // 200 OK

$this->send_json(array(
  "hello" => "world!",
), 400); // 400 Bad Request

$this->send_json(array(
  "hello" => "world!",
), 500); // 500 Internal Error
```

---

### send_json_body()

> JSON response function, allows you to send data as JSON. Response will always have the Content-Type of `application/json`. Status code is chosen by passing `status` in the `$data` object/array. `$data` can either be an associative array or an object. If `status` is omitted from `$data` then it will default to `200`. HTTP status code must be a number _(100-599)_.

```php
function send_json_body(object|array $data, bool $include_status_code = true)
```

**Examples:**

```php
$this->send_json_body(array(
  "hello" => "world!",
)); // 200 OK

$this->send_json_body(array(
  "status" => 200,
  "hello" => "world!",
)); // 200 OK

$this->send_json_body(array(
  "status" => 400,
  "hello" => "world!",
)); // 400 Bad Request

$this->send_json_body(array(
  "status" => 500,
  "hello" => "world!",
)); // 500 Internal Error
```

---

### send_file()

> File response function, allows you to send files. Response will automatically choose the Content-Type if `finfo` is supported _(will throw an error if it's not supported)_. Status code is always set to `200`. `$download_file_name` will change the file name displayed to the user. `stream` will stream the file rather than sending it all at once.

```php
function send_file(string $file_path, string|null $download_file_name = null, string|null $content_type = null, bool $stream = false)
```

**Examples:**

```php
// it's recommended to check if the file is readable first, since otherwise it will error out
if(is_readable("hello_world.txt")) {
  $this->send_file("hello_world.txt");
}

// it's recommended to check if the file is readable first, since otherwise it will error out
if(is_readable("hello_world.txt")) {
  $this->send_file("hello_world.txt", "i_show_up_differently_to_the_user.txt");
}
```

---

## Moving uploaded files

> This function moves an uploaded file to a specified location. Returns the uploaded file's path on success.

```php
function move_uploaded_file(string $file, string $dest_folder = ".", string $new_file_name = "", string $new_file_ext = ""): string
```

### Exceptions

- If no files are found in the request, an exception with error code `0` is thrown.
- If the uploaded file is not found under the property name provided by `$file`, an exception with error code `1` is thrown.
- If an error occurs with the upload, an exception with error code `2` is thrown.
- If the specified folder path by `$dest_folder` is not writeable, an exception with error code `3` is thrown.
- If the specified `$dest_folder` is not a folder, an exception with error code `4` is thrown.
- If the file cannot be moved, an exception with error code `5` is thrown.

**Examples:**

```php
// wrapping move_uploaded_file() in a try block is recommended, since it throws exceptions when errors are encountered
try {
  // moves the file uploaded under the name "cv" to the "uploaded_files" folder
  $uploaded_file = $this->move_uploaded_file("cv", "uploaded_files"); // returns the uploaded file's path (e.g. "uploaded_files/John Doe - CV.pdf")

  return $this->send_json_body(array(
    "status" => 200,
    "message" => "Thank you for uploading your CV.",
    "uploaded_path" => $uploaded_file
  ));
} catch(Exception $err) {
  return $this->send_json_body(array(
    "status" => 400,
    "error" => $err->getMessage(),
    "error_code" => $err->getCode()
  ));
}
```

---

## HTML rendering

> A HTTP method can be specified, the allowed values are: `ALL`, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`. The provided method does not have to be all uppercase.

```php
function render_html(string $route_str, callable $route_callback, $status_code = 200, string $method = "GET")
```

**Examples:**

`/routes/root.php`

```php
<?php $this->render_html("/", function() { ?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hello world!</title>
  </head>

  <body>
    <p>Hello world! <?php echo $this->request->uri; ?></p>
  </body>
</html>
<?php }); ?>
```

`/routes/document.php`

```php
<?php

$this->render_html("/document/:id", function() {
  // ... logic to fetch document ...
?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document viewer</title>
  </head>

  <body>
    <h1><?php echo $document->title; ?></h1>
    <main><?php echo $document->contents; ?></main>
  </body>
</html>
<?php }, 200); ?>
```

---

## View rendering

> A HTTP method can be specified, the allowed values are: `ALL`, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`. The provided method does not have to be all uppercase.

```php
function render_view(string $route_str, string $view_str, $status_code = 200, string $method = "GET")
```

> `$view_str` should be the name of the file inside the `views` folder. So `document` would turn into `views/document.php`, `hello/world` would turn into `views/hello/world.php`.

**Examples:**

`/routes/main.php`

```php
<?php

$this->render_view("/", "main");
$this->render_view("/document/:id", "document");

?>
```

`/views/main.php`

```php
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hello world!</title>
  </head>

  <body>
    <p>Hello world! <?php echo $this->request->uri; ?></p>
  </body>
</html>
```

`/views/document.php`

```php
<?php
  // ... logic to fetch document ...
?>
<!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document viewer</title>
  </head>

  <body>
    <h1><?php echo $document->title; ?></h1>
    <main><?php echo $document->contents; ?></main>
  </body>
</html>
```

---

## Authentication

### Bearer Token

You can use the provided `parse_auth()` method to parse for a Bearer token. If a valid Authorization HTTP header is found and the parsing is successful then the token will be added to `request->token`. If no valid token can be found then `request->token` will be `null`.

**Example:**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->parse_auth(); // activate parsing of Basic Authentication & Bearer tokens

$webFramework->get("/protected", function() use($webFramework) {
  $token = $webFramework->request->token;

  if($token === null) {
    return $webFramework->send_json_body(array(
      "status" => 403,
      "message" => 'Missing or invalid Authorization header provided (use: "Authorization: Bearer ...")!',
    ));
  }

  if($token !== "secret_token") {
    return $webFramework->send_json_body(array(
      "status" => 403,
      "message" => 'Provided token is invalid!',
    ));
  }

  return $webFramework->send_json_body(array(
    "status" => 200,
    "message" => "Hello protected world!",
  ));
});

$webFramework->start();

?>
```

### Basic Authentication

You can use the provided `parse_auth()` method to parse for Basic Authentication requests. If a valid Authorization HTTP header is found and the parsing is successful then the provided username and password will be added to `request->credentials`. If no valid username and password can be found then `request->credentials` will be `null`.

> The **username** & **password** in `request->credentials` get trimmed of leading and following spaces, since `"  john.doe"` or `"password  "`, are not desired.

**Example:**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->parse_auth(); // activate parsing of Basic Authentication & Bearer tokens

$webFramework->get("/protected", function() use($webFramework) {
  $credentials = $webFramework->request->credentials;

  if($credentials === null) {
    return $webFramework->send_json_body(array(
      "status" => 403,
      "message" => 'Missing or invalid Authorization header provided (use: "Authorization: Basic ...")!',
    ));
  }

  if(
    $credentials["username"] !== "john.doe" &&
    $credentials["password"] !== "password"
    ) {
    return $webFramework->send_json_body(array(
      "status" => 403,
      "message" => 'Provided credentials are invalid!',
    ));
  }

  return $webFramework->send_json_body(array(
    "status" => 200,
    "message" => "Hello protected world!",
  ));
});

$webFramework->start();

?>
```

---

## Custom 404 response

You can easily customize the provided 404 response for any HTTP method by settings the route URI to: `:404`. Customization can be done on a per HTTP method way, or for all methods using `all()`.

> Custom 404's can also use the `render_html()` method for rendering more complex pages.

**Example:**

```php
<?php

// example #1: special "ALL" request handling (will handle GET, POST, PUT, etc.)
$this->all(":404", function() {
  $this->send("No route found!", 404);
});

// example #2: GET request handling
$this->get(":404", function() {
  $this->send_json_body(array(
    "status" => 404,
    "message" => "No route found!",
  ));
});

// example #3: POST request handling
$this->post(":404", function() {
  $this->send_json_body(array(
    "status" => 404,
    "message" => "No route found!",
  ));
});

?>
```

---

## Custom error handler

You can easily customize the default error handler that is provided. This error handler will deal with fatal errors and exceptions.

> Debug mode can be turned on to get more detailed error messages.

> Error handling can also be disabled in the constructor, e.g. `$webFramework = new WebFramework("routes", false);`.

**Example:**

```php
<?php

$webFramework = new WebFramework(array(
  "debug_mode" => true // use this if you want more detailed messages (not recommended for production)
));

$webFramework->set_custom_error_handler(function(int $error_code, string $error_message) use($webFramework) {
  $webFramework->send_json_body(array(
    "status" => 500,
    "message" => "Something went wrong, please try again later",
    "error" => array(
      "code" => $error_code,
      "message" => $error_message,
    )
  ));
});

?>
```

---

## Custom headers

You can send custom HTTP headers either for all routes or on a per route basis.

**Example all routes:**

```php
<?php

require_once("./classes/WebFramework.php");

// Example HTTP header (will be activated for all loaded routes)
header("Strict-Transport-Security: max-age=15552000; includeSubDomains");

$webFramework = new WebFramework();
// Custom headers can be set at any point until "$webFramework->start();"
$webFramework->start();

?>
```

**Example one routes:**

```php
<?php

$this->get("/hello", function() {
  // Example HTTP header (will be activated for this route only)
  header("Strict-Transport-Security: max-age=15552000; includeSubDomains");

  $this->send_json_body(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

?>
```

---

## Helmet

> This framework includes [Helmet's (JS)](https://helmetjs.github.io/) defaults that can be activated by calling `$webFramework->helmet()`, this must be called before `$webFramework->start()`.

**Example:**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->helmet(); // activate Helmet
$webFramework->start();

?>
```

---

## CORS

Implementing CORS handling is not something I feel is necessary. The point of this framework is to only provide what is necessary and potential security benefits. As such if you want to add CORS handling you can add it to all loaded routes by adding it above your call to `start()`. Also since CORS can be handled in various ways, implementing it would be counter productive.

> CORS handling can also be added on a per route basis. If this is done then it has to be added before `send()`, `send_json()`, `send_json_body()` or `render_html()`.

**Example all routes:**

```php
<?php

require_once("./classes/WebFramework.php");

// Simple example CORS headers (will be activated for all loaded routes)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,HEAD,PUT,PATCH,POST,DELETE");

$webFramework = new WebFramework();
// CORS headers can be set at any point until "$webFramework->start();"
$webFramework->start();

?>
```

**Example one routes:**

```php
<?php

$this->get("/hello", function() {
  // Simple example CORS headers (will be activated for this route only)
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Methods: GET,HEAD,PUT,PATCH,POST,DELETE");

  $this->send_json_body(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

?>
```
