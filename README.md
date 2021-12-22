# WebFrameworkPHP

A small and simple web framework built using PHP. Handles routing and different request methods.

---

## Installation

Add `WebFramework.php` to your project and require it in `index.php`. To allow for routing a `.htaccess` file is used.

> This framework requires **PHP 8.0+** to work, this is due to the usage of the `object` type hint and the use of `str_starts_with`.

**index.php**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework(); // default with auto loading from "routes" folder

// ... optional manual loading of additional routes ...

$webFramework->start(); // runs last (after all routes have been loaded)

?>
```

**Options 1: `.htaccess` when running framework at domain root _[`https://example.com/`]_**

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

**Options 2: `.htaccess` when running framework inside a folder _[`https://example.com/my_api/`]_**

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

> All routing is done by placing PHP files inside either the default `routes` folder, or by choosing your own folder in the constructor. Auto loading of routes can be disabled by passing an empty string to the constructor _(e.g. `""`)_. Auto loading can used together with manual loading of additional routes.

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

$webFramework = new WebFramework(""); // "" => disables auto loading
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

> Base response function, allows you to send any data you want with any Content-Type and status code.

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

> JSON response function, allows you to send data as JSON. Response will always have the Content-Type of `application/json`. Status code is chosen by passing `status` in the `$data` object/array. `$data` can either be an associative array or an object. If `status` is omitted from `$data` then it will default to `200`.

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

> `request->uri` will always exclude the current directory, if you are running the framework from the domain root then this will not affect you. However if you are running from inside a folder, the URI will never include the folder, e.g. `/my_api/hello` becomes `/hello`.

**Structure of request data:**

```php
$this->request = (object) array(
  "method" => $_SERVER["REQUEST_METHOD"], // HTTP method of the request
  "content_type" => $_SERVER["CONTENT_TYPE"], // Content-Type of the request
  "uri" => "...", // the current URI
  "token" => "...", // the parsed bearer token of the request (only gets parsed if the auth() method is called before start()) [will be null if not found]
  "query" => array(...), // parsed URI queries (?hello=world&abc=123)
  "params" => array(...), // parsed URI params (/:hello/:abc)
  "body" => array(...), // parsed post data (form-data, x-www-form-urlencoded, raw[application/json]) (will not be parsed if HTTP method is "GET")
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
  $this->request->token; // "my_secret_token" (can be null, either if Bearer token parsing wasn't enabled using auth() or if a valid HTTP header couldn't be found in the request)
  $this->request->params["id"]; // "12345678" (required)
  $this->request->query["type"]; // "sticky" (can be missing, using isset() before accessing is recommended)
  $this->request->body["title"]; // "My sticky note" (can be missing, using isset() before accessing is recommended)
  $this->request->body["contents"]; // "Remember Sunday" (can be missing, using isset() before accessing is recommended)
  $this->send("Note added");
}
```

---

## Handling Bearer tokens

You can use the provided `auth()` method to parse for a Bearer token. If a valid Authorization HTTP header is found and the parsing is successful then the token will be added to `request->token`. If no valid token can be found then `request->token` will be `null`.

**Example:**

```php
<?php

require_once("./classes/WebFramework.php");

$webFramework = new WebFramework();
$webFramework->auth(); // activate parsing of Bearer token
$webFramework->start();

?>
```

---

## HTML rendering

> HTML can also be rendered at specified routes using the `render_html()` method.

### Examples

**/routes/root.php**

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

**/routes/document.php**

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
