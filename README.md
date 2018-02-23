# PHP Routing

[![Build Status](https://travis-ci.org/razonyang/php-routing.svg?branch=master)](https://travis-ci.org/razonyang/php-routing)
[![Coverage Status](https://coveralls.io/repos/github/razonyang/php-routing/badge.svg?branch=master)](https://coveralls.io/github/razonyang/php-routing?branch=master)
[![Latest Stable Version](https://poser.pugx.org/razonyang/php-routing/v/stable.svg)](https://packagist.org/packages/razonyang/php-routing)
[![Total Downloads](https://poser.pugx.org/razonyang/php-routing/downloads.svg)](https://packagist.org/packages/razonyang/php-routing)
[![License](https://poser.pugx.org/razonyang/php-routing/license.svg)](LICENSE)

A fast, flexible and scalable HTTP router for PHP.

## Features

- **Easy to design RESTful API**
- **Full Tests**
- **Flexible and scalable**: it allows you to define your own handler to deal with [`Not Found`](#not-found-handler), [`Method Not Allowed`](#method-not-allowed-handler) and [`OPTIONS`](#options-handler) request.
- **No third-party library dependencies**
- **Named Param Placeholder**
- **Detect all request methods of the specify path**
- **Straightforward documentation**

## Requirements

- PHP - `7.0`, `7.1`, `7.2` and `master` are supported.

## Install

```
composer require razonyang/php-routing:0.1.0
```

## Documentation

```php
include '/path-to-vendor/autoload.php';

use RazonYang\Routing\Router;

// create an router instance
$router = new Router();
```

### Register handler

```php
Router::handle($method, $path, $handler);
```

- `method` - `string` or `array`, **case-sensitive**, such as `GET`, `GET|POST`(split by `|`, without spaces), `['GET', 'POST']`
- `path` - the path **MUST** start with slash `/`, such as `/`, `/users`, `/users/<username>`.
- `handler` - `mixed`, whatever you want.


Examples

| Method                     | Path                           | Handler | Matched                            | Unmatched                              |
|:---------------------------|:-------------------------------|:--------|:-----------------------------------|----------------------------------------|
| `GET`                      | `/`                            | handler | `GET /`                            | `POST /` `get /`                       |
| <code>GET&#124;POST</code> | `/users`                       | handler | `GET /users` `POST /users`         |                                        |
| `['GET', 'POST']`          | `/merchants`                   | handler | `GET /merchants` `POST /merchants` |                                        |
| `GET`                      | `/users/<username>`            | handler | `GET /users/foo` `GET /users/bar`  |                                        |
| `GET`                      | `/orders/<order_id:\d+>`       | handler | `GET /orders/123456`               | `GET /orders/letters`                  |

It also provides a few shortcuts for registering handler:

- `Router::delete`
- `Router::get`
- `Router::post`
- `Router::put`

```php
$router->get('/', 'handler');

$router->handle('GET|POST', '/users', 'handler');

$router->handle(['GET', 'POST'], '/merchants', 'handler');

$router->get('/users/<username>', 'handler');

$router->get('/orders/<order_id:\d+>', 'handler');
```

### Dispatch request

```php
Router::dispatch($method, $path);
```

- `method` - request method.
- `path` - URI path.

If matched, a [`Route`](src/Route.php) instance will be returns, `null` otherwise or NotFoundException/MethodNotAllowedException will be thrown.

```php
$path = '/users/baz';
$route = $router->dispatch(Router::METHOD_GET, $path);

// handle requset
$handler = $route->handler; // 'handler'
$params = $route->params; // ['username' => 'baz']
```

### Named Params Placeholder

As the examples shown above, Router has ability to detect the param's value of the path.

In general, an placeholder pattern MUST be one of `<name>` and `<name:regex>`, it will be 
converted to `([^/]+)` and `(regex)` respectively.
You can also change it via replace the `Router::$replacePatterns` and `Router::$replacements`.

| Pattern                                     | Path                                       | Matched | Params |
|:--------------------------------------------|:-------------------------------------------|:--------|:-----------------------------------------------------------------|
| `/guests/<name>`                            | `/guests/小明`                              | YES     | `['name' => '小明']`                                              |
| `/guests/<name:\w+>`                        | `/guests/foo`                              | YES     | `['name' => 'foo']`                                              |
| `/guests/<name:\w+>`                        | `/guests/小明`                              | NO      |                                                                  |
| `/orders/<order_id:\d+>`                    | `/orders/123`                              | YES     | `['order_id' => '123']`                                          |
| `/orders/<order_id:\d+>`                    | `/orders/letters`                          | NO      |                                                                  |
| `/posts/<year:\d{4}>/<month:\d{2}>/<title>` | `/posts/2017/10/hello-world`               | YES     | `['year' => '2017', 'month' => '10', title' => 'hello-world']`   |
| `/posts/<year:\d{4}>/<month:\d{2}>/<title>` | `/posts/201/10/hello-world`                | NO      |                                                                  |
| `/posts/<year:\d{4}>/<month:\d{2}>/<title>` | `/posts/2017/9/hello-world`                | NO      |                                                                  |
| `/posts/<year:\d{4}><month:\d{2}>/<title>`  | `/posts/201710/hello-world`                | YES     | `['year' => '2017', 'month' => '10', title' => 'hello-world']`   |

### RESTful API

As the examples shown above, it is obviously easy to design a RESTful API application.

```php
$router->get('/products', 'products');
$router->post('/products', 'create product');
$router->get('/products/<product_id:\d+>', 'product detail');
$router->put('/products/<product_id:\d+>', 'update product');
$router->delete('/products/<product_id:\d+>', 'delete product');
```

### Not Found Handler

```php
$router->notFoundHandler = function($method, $path) {
    throw new \Exception('404 Not Found');
};
```

### Method Not Allowed Handler

```php
$router->methodNotAllowedHandler = function($method, $path, $allowedMethods) {
    throw new \Exception('405 Method Not Allowed');
};
```

### OPTIONS Handler

```php
$router->optionsHandler = function($method, $path, $allowedMethods) {
    header('Allow: ' . implode(',', $allowedMethods));
};
```

## FAQ

### Package Not Found

Please add the following repository into `repositories` when `composer` complains about
that `Could not find package razonyang/php-routing ...`.

```json
{
    "type": "git",
    "url": "https://github.com/razonyang/php-routing.git"
}
```