<?php
namespace RazonYang\Routing;

class Route
{
    /**
     * @var string request method.
     */
    public $method;

    /**
     * @var string request path.
     */
    public $path;

    /**
     * @var mixed $handler .
     */
    public $handler;

    /**
     * @var array $params .
     */
    public $params;

    /**
     * @var bool whether or not the path is end with slash.
     */
    public $hasTrailingSlash = false;

    public function __construct($method, $path, $handler, $params, $hasTrailingSlash)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->params = $params;
        $this->hasTrailingSlash = $hasTrailingSlash;
    }
}