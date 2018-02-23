<?php

use PHPUnit\Framework\TestCase;
use RazonYang\Routing\MethodNotAllowedException;
use RazonYang\Routing\NotFoundException;
use RazonYang\Routing\Route;
use RazonYang\Routing\Router;

class RouterTest extends TestCase
{
    private $class;

    /**
     * @covers RazonYang\Routing\Router
     */
    public function testEmptyRouter()
    {
        $router = new Router();

        $this->assertEquals([], $this->getPropertyValue($router, 'routes'));
        $this->assertEquals([], $this->getPropertyValue($router, 'patterns'));
        $this->assertEquals(null, $this->getPropertyValue($router, 'combinedPattern'));
        $this->assertEquals(1, $this->getPropertyValue($router, 'routesIndex'));
        $this->assertEquals(
            ['#<([^:]+)>#', '#<([^:]+):([^>]+)>?#', '#/$#'],
            $this->getPropertyValue($router, 'replacePatterns')
        );
        $this->assertEquals(
            ['([^/]+)', '($2)', ''],
            $this->getPropertyValue($router, 'replacements')
        );

        return $router;
    }

    /**
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testHandleEmptyPath($router)
    {
        $this->expectExceptionMessage('path MUST begin with slash');

        $router->handle(Router::METHOD_GET, '', '');
    }

    /**
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testHandleInvalidPath($router)
    {
        $this->expectExceptionMessage('path MUST begin with slash');

        $router->handle(Router::METHOD_GET, 'notBeginWithSlash', '');
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testHandleMultipleMethods($router)
    {
        $path = '/multiple-methods';
        $router->handle(Router::METHOD_GET . '|' . Router::METHOD_POST, $path, 'multiple-methods');
        $this->assertNotEquals(null, $router->dispatch(Router::METHOD_GET, $path));
        $this->assertNotEquals(null, $router->dispatch(Router::METHOD_POST, $path));

        $path2 = '/multiple-methods2';
        $router->handle([Router::METHOD_GET, Router::METHOD_POST], $path2, 'multiple-methods2');
        $this->assertNotEquals(null, $router->dispatch(Router::METHOD_GET, $path2));
        $this->assertNotEquals(null, $router->dispatch(Router::METHOD_POST, $path2));
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     * @covers  RazonYang\Routing\NotFoundException
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testNotFound($router)
    {
        $this->expectException(\RazonYang\Routing\NotFoundException::class);

        $router->dispatch(Router::METHOD_GET, '/not-present');
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testNotFoundHandler($router)
    {
        $handled = false;
        $router->notFoundHandler = function ($method, $path) use (&$handled) {
            $handled = true;
        };
        $router->dispatch(Router::METHOD_GET, '/not-present');
        $this->assertEquals(true, $handled);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     * @covers  RazonYang\Routing\MethodNotAllowedException
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testMethodNotAllowed($router)
    {
        $this->expectException(MethodNotAllowedException::class);

        $router->get('/users', '');
        $router->dispatch(Router::METHOD_POST, '/users');
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     * @covers  RazonYang\Routing\MethodNotAllowedException
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testMethodNotAllowedHandler($router)
    {
        $handled = false;
        $methods = [];
        $router->methodNotAllowedHandler = function ($method, $path, $allowedMethods) use (&$handled, &$methods) {
            $handled = true;
            $methods = $allowedMethods;
        };
        $router->put('/users/<id>', '');
        $router->post('/users/<id>', '');
        $router->dispatch(Router::METHOD_DELETE, '/users/1');
        $this->assertEquals(true, $handled);
        $this->assertCount(2, $methods);
        $this->assertContains(Router::METHOD_PUT, $methods);
        $this->assertContains(Router::METHOD_POST, $methods);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     * @covers  RazonYang\Routing\MethodNotAllowedException
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @runInSeparateProcess
     *
     * @throws
     */
    public function testOptionsHandler($router)
    {
        $router->delete('/users/<id>', '');
        $router->dispatch(Router::METHOD_OPTIONS, '/users/1');
        $this->assertContains('Allow: ' . Router::METHOD_DELETE, xdebug_get_headers());

        // specify OPTIONS handler.
        $handled = false;
        $methods = [];
        $router->optionsHandler = function ($method, $path, $allowedMethods) use (&$handled, &$methods) {
            $handled = true;
            $methods = $allowedMethods;
        };
        $router->dispatch(Router::METHOD_OPTIONS, '/users/1');
        $this->assertEquals(true, $handled);
        $this->assertCount(1, $methods);
        $this->assertEquals(Router::METHOD_DELETE, $methods[0]);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testParamPlaceholder1($router)
    {
        // one param placeholder which can matches any type value
        $handler = 'user profile';
        $router->get('/users/<username>', $handler);
        // string
        $route1 = $router->dispatch(Router::METHOD_GET, '/users/foo');
        $this->assertEquals($handler, $route1->handler);
        $this->assertArrayHasKey('username', $route1->params);
        $this->assertEquals('foo', $route1->params['username']);
        // int
        $route2 = $router->dispatch(Router::METHOD_GET, '/users/123456');
        $this->assertEquals($handler, $route2->handler);
        $this->assertArrayHasKey('username', $route2->params);
        $this->assertEquals('123456', $route2->params['username']);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     * @covers  RazonYang\Routing\NotFoundException
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testParamPlaceholder2($router)
    {
        // one param placeholder which can matches specify type value.
        $handler = 'order detail';
        // matches integer only
        $router->get('/orders/<order_id:\d+>', $handler);

        // matches numeric params.
        $route1 = $router->dispatch(Router::METHOD_GET, '/orders/654321');
        $this->assertEquals($handler, $route1->handler);
        $this->assertArrayHasKey('order_id', $route1->params);
        $this->assertEquals('654321', $route1->params['order_id']);

        // dose not match string.
        $this->expectException(NotFoundException::class);
        $router->dispatch(Router::METHOD_GET, '/orders/bar');
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testParamPlaceholder3($router)
    {
        // multiple param placeholders
        $handler = 'post detail';
        $router->get('/posts/<year:\d{4}>/<month:\d{2}>/<title>', $handler);

        // valid year and month
        $route1 = $router->dispatch(Router::METHOD_GET, '/posts/2018/02/hello-world');
        $this->assertEquals($handler, $route1->handler);
        $this->assertEquals('2018', $route1->params['year']);
        $this->assertEquals('02', $route1->params['month']);
        $this->assertEquals('hello-world', $route1->params['title']);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testTrailingSlash($router)
    {
        $router->get('/', '');

        // without trailing slash.
        $route1 = $router->dispatch(Router::METHOD_GET, '');
        $this->assertEquals(false, $route1->hasTrailingSlash);

        // with trailing slash.
        $route2 = $router->dispatch(Router::METHOD_GET, '/');
        $this->assertEquals(false, $route2->hasTrailingSlash);
    }

    /**
     * @covers  RazonYang\Routing\Route
     * @covers  RazonYang\Routing\Router
     *
     * @depends clone testEmptyRouter
     *
     * @param Router $router
     *
     * @throws
     */
    public function testTrailingSlash2($router)
    {
        $router->get('/hello', '');

        // without trailing slash.
        $route1 = $router->dispatch(Router::METHOD_GET, 'hello');
        $this->assertEquals(false, $route1->hasTrailingSlash);

        // with trailing slash.
        $route2 = $router->dispatch(Router::METHOD_GET, 'hello/');
        $this->assertEquals(true, $route2->hasTrailingSlash);
    }

    /**
     * @param Object $obj
     * @param string $name
     * @return mixed
     */
    private function getPropertyValue(&$obj, $name)
    {
        if (!$this->class) {
            $this->class = new ReflectionClass(Router::class);
        }
        $property = $this->class->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}