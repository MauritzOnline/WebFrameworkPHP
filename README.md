# WebFrameworkPHP

A small and simple web framework built using PHP. Handles routing and different request methods.

---

## Installation

Add `WebFramework.php` to your project and require it in `index.php`. To allow for routing a `.htaccess` file is used.

**index.php**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework(); // default with auto loading from "routes" folder

// ... optional manual loading of additional routes ...

$webFramework->start(); // runs last (after all routes have been loaded)

?>
```

**Options 1: `.htaccess` at domain root _[`https://example.com/`]_**

```apacheconf
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# if forcing of https is wanted (excluding localhost)
RewriteCond %{HTTP_HOST} !=localhost
RewriteCond %{HTTPS} !=on
RewriteRule ^.*$ https://%{SERVER_NAME}%{REQUEST_URI} [R,L]
```

**Options 2: `.htaccess` inside a folder _[`https://example.com/my_api/`]_**

```apacheconf
RewriteEngine On
RewriteBase /my_api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# if forcing of https is wanted (excluding localhost)
RewriteCond %{HTTP_HOST} !=localhost
RewriteCond %{HTTPS} !=on
RewriteRule ^.*$ https://%{SERVER_NAME}%{REQUEST_URI} [R,L]
```

---

## Route loading

> All routing is done by placing PHP files in to either the default `routes` folder, or by choosing your own folder in the constructor. Auto loading of routes can be disabled by passing an empty string to the constructor _(e.g. `""`)_. Auto loading can used together with manual loading of additional routes.

### Auto loading

**/index.php**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->start();

?>
```

**/routes/demo.php**

```php
<?php

$this->get("/demo", function() {
  $this->send_json(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

// ... additional paths ...

?>
```

### Manual loading

**/index.php**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework("");
require_once("./manual_route.php");
$webFramework->start();

?>
```

**/manual_route.php**

```php
<?php

$webFramework->get("/manual", function() {
  global $webFramework;

  $webFramework->send_json(array(
    "status" => 200,
    "message" => "Hello world!",
  ));
});

// ... additional paths ...

?>
```

---

## Routing

> The following HTTP methods are available: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`. URI queries in the route URI will be ignored, e.g. `"/document?hello=world"` will resolve to `/document`, as such URI queries should not be used in the route URI.

```php
<?php

// example #1: GET request handling
$this->get("/document", function() {
  // ... documents ...

  $this->send_json(array(
    "status" => 200,
    "message" => "Fetched all documents!",
    "documents" => array [...],
  ));
});

// example #2: GET request handling, with URI param
$this->get("/document/:id", function() {
  $document_id = $this->request->params["id"];

  // ... fetch document ...

  $this->send_json(array(
    "status" => 200,
    "message" => "Fetched single document!",
    "document" => object {...},
  ));
});

// example #3: POST request handling
$this->post("/document", function() {
  $document_title = $this->request->body["title"];
  $document_contents = $this->request->body["contents"];

  // ... create document ...

  $this->send_json(array(
    "status" => 200,
    "message" => "New document added!",
    "id" => $document_id
  ));
});

?>
```

---

## Sending responses

> Responses can either be sent using `send(...)` or `send_json(...)`. Responses exit PHP, so after they are sent no other code will be run.

### send()

> You are free to choose Content-Type yourself and status code.

```php
public function send(string $data, int $status_code = 200, string $content_type = "text/plain") { ... }
```

**Examples:**

```php
$this->send("Hello world!"); // 200 OK
$this->send("Hello world!", 400); // 400 Bad Request
$this->send("Hello world!", 500); // 500 Internal Error
$this->send(json_encode(array(...)), 200, "application/json"); // 200 OK
```

### send_json()

> Response will always have the Content-Type of `application/json`. Status code is chosen by passing `status` in the `$data` object/array. `$data` can either be an associative array or an object. If `status` is omitted from `$data` then it will default to `200`.

```php
public function send_json(object|array $data) { ... }
```

**Examples:**

```php
$this->send_json(array(
  "hello" => "world!",
)); // 200 OK

$this->send_json(array(
  "status" => 200,
  "hello" => "world!",
)); // 200 OK

$this->send_json(array(
  "status" => 400,
  "hello" => "world!",
)); // 400 Bad Request

$this->send_json(array(
  "status" => 500,
  "hello" => "world!",
)); // 500 Internal Error
```

---

## Request data

**Structure of request data:**

```php
$this->request = (object) array(
  "method" => $_SERVER["REQUEST_METHOD"],
  "content_type" => $_SERVER["CONTENT_TYPE"],
  "uri" => "/", // the current URI
  "query" => array(...), // parsed URI queries (?hello=world&abc=123)
  "params" => array(...), // parsed URI params (/:hello/:abc)
  "body" => array(...), // parsed post data (form-data, x-www-form-urlencoded, raw[application/json]) (will not be parsed if HTTP method is "GET")
);
```

**Examples:**

```php
$this->post("/:hello/:abc", function() {
  $this->request->query["hello"]; // world (required)
  $this->request->params["hello"]; // world (can be missing, as such using isset before accessing is recommended)
  $this->request->body["hello"]; // world (can be missing, as such using isset before accessing is recommended)
  $this->send("");
}
```
