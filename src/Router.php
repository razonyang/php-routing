<?php
namespace RazonYang\Routing;

/**
 * Class Router is a fast, flexible and powerful HTTP router.
 */
class Router
{
    const METHOD_CONNECT = 'CONNECT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_PATCH = 'PATCH';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_TRACE = 'TRACE';

    public static $methods = [
        self::METHOD_CONNECT,
        self::METHOD_DELETE,
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_OPTIONS,
        self::METHOD_PATCH,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_TRACE,
    ];

    const TRAILING_SLASH_POLICY_IGNORE = 1;
    const TRAILING_SLASH_POLICY_STRICT = 2;

    public $trailingSlashPolicy = self::TRAILING_SLASH_POLICY_STRICT;

    /**
     * @var array a set of route.
     */
    private $routes = [];

    /**
     * @var int a trick for dispatch and handle a request.
     * @see handle()
     * @see dispatch()
     */
    private $routesIndex = 1;

    /**
     * @var array a set of route's patterns.
     */
    private $patterns = [];

    /**
     * @var null|string a combined pattern of all patterns.
     */
    private $combinedPattern;

    /**
     * @var mixed
     * @see handle()
     */
    public $replacePatterns = [
        '#<([^:]+)>#',
        '#<([^:]+):([^>]+)>?#',
        '#/$#'
    ];

    /**
     * @var mixed
     * @see handle()
     */
    public $replacements = [
        '([^/]+)',
        '($2)',
        ''
    ];

    /**
     * @var callable function to handle Not Found request,
     * form:
     *     function($method, $path) {
     *
     *     }.
     */
    public $notFoundHandler;

    /**
     * @var callable function to handle Method Not Allowed request,
     * form:
     *     function($method, $path, $allowedMethods) {
     *
     *     }.
     */
    public $methodNotAllowedHandler;

    /**
     * @var callable function to handle OPTIONS request,
     * form:
     *     function($method, $path, $allowedMethods) {
     *
     *     }.
     */
    public $optionsHandler;

    /**
     * Registers a handler for handling the specific request which
     * relevant to the given method and path.
     *
     * @param string|array $method request method, is case sensitive,
     * it is RECOMMENDED to use uppercase.
     *     method              validity
     *     "GET"               valid(RECOMMENDED)
     *     "GET|POST"          valid(RECOMMENDED)
     *     "GET,POST"          invalid
     *     ['GET', 'POST']     valid
     *
     * @param string $path the regular expression, the path MUST start with '/', if not,
     * an \Exception will be thrown.
     * Param pattern MUST be one of "<name>" and "<name:regex>", in default,
     * it will be converted to "([^/]+)" and "(regex)" respectively.
     * The path will be converted to a pattern by preg_replace(@see $replacePatterns, @see $replacements),
     * you can change it in need.
     *
     * @param mixed $handler request handler.
     *
     * @throws \Exception
     *
     * Examples:
     *     path                              matched
     *     "/users"                           "/users"
     *     "/users/<id:\d+>"                  "/users/123"
     *     "/users/<id:\d+>/posts"            "/users/123/posts"
     *     "/users/<id:\d+>/posts/<post>"     "/users/123/posts/456", "/users/123/posts/post-title"
     */
    public function handle($method, string $path, $handler)
    {
        if (empty($path) || $path[0] != '/') {
            throw new \Exception('path MUST begin with slash');
        }

        if (is_array($method)) {
            $method = implode('|', $method);
        }
        $method = strtoupper($method);

        // format path to regular expression.
        $pattern = preg_replace($this->replacePatterns, $this->replacements, $path);
        // store pattern
        $this->patterns[$this->routesIndex] = "({$method})\s+{$pattern}(/?)";

        // collect param's name.
        preg_match_all('/<([^:]+)(:[^>]+)?>/', $path, $matches);
        $params = empty($matches[1]) ? [] : $matches[1];
        $this->routes[$this->routesIndex] = [$handler, $params];

        // calculate the next index of routes.
        $this->routesIndex += count($params) + 2;

        // set combinedPattern as null when routes has been changed.
        $this->combinedPattern = null;
    }

    /**
     * A shortcut for registering a handler to handle GET request.
     *
     * @see handle()
     *
     * @param $path
     * @param $handler
     *
     * @throws \Exception
     */
    public function get($path, $handler)
    {
        $this->handle(self::METHOD_GET, $path, $handler);
    }

    /**
     * A shortcut for registering a handler to handle DELETE request.
     *
     * @see handle()
     *
     * @param $path
     * @param $handler
     *
     * @throws \Exception
     */
    public function delete($path, $handler)
    {
        $this->handle(self::METHOD_DELETE, $path, $handler);
    }

    /**
     * A shortcut for registering a handler to handle POST request.
     *
     * @see handle()
     *
     * @param $path
     * @param $handler
     *
     * @throws \Exception
     */
    public function post($path, $handler)
    {
        $this->handle(self::METHOD_POST, $path, $handler);
    }

    /**
     * A shortcut for registering a handler to handle PUT request.
     *
     * @see handle()
     *
     * @param $path
     * @param $handler
     *
     * @throws \Exception
     */
    public function put($path, $handler)
    {
        $this->handle(self::METHOD_PUT, $path, $handler);
    }

    /**
     * Retrieves handler with the given method and path.
     *
     * @param string $method request method
     * @param string $path request URL without the query string.
     *
     * @return null|Route if matched, returns an instance of Route,
     * otherwise null will be returned.
     *
     * @throws NotFoundException|MethodNotAllowedException
     */
    public function dispatch(string $method, string $path)
    {
        $pattern = $this->getCombinedPattern();
        if ($pattern != null) {
            $method = strtoupper($method);
            $path = $this->formatPath($path);
            if (preg_match($pattern, $method . ' ' . $path, $matches)) {
                // retrieves route
                for ($i = 1; $i < count($matches) && ($matches[$i] === ''); $i++) ;
                $route = $this->routes[$i];

                // fills up param's value
                $params = [];
                foreach ($route[1] as $param) {
                    $params[$param] = $matches[++$i];
                }

                return new Route($method, $path, $route[0], $params, $path != '/' && $matches[++$i] == '/');
            }

            $allowedMethods = $this->getAllowedMethods($path);

            // handle OPTIONS request.
            if ($method == self::METHOD_OPTIONS) {
                if ($this->optionsHandler != null) {
                    call_user_func($this->optionsHandler, $method, $path, $allowedMethods);
                    return null;
                }

                if (count($allowedMethods) > 0) {
                    header('Allow: ' . implode(',', $allowedMethods));
                }
                return null;
            }

            // handle Method Not Allowed request.
            if (count($allowedMethods) > 0) {
                if ($this->methodNotAllowedHandler != null) {
                    call_user_func($this->methodNotAllowedHandler, $method, $path, $allowedMethods);
                    return null;
                }

                throw new MethodNotAllowedException();
            }
        }

        if ($this->notFoundHandler != null) {
            call_user_func($this->notFoundHandler, $method, $path);
            return null;
        }

        throw new NotFoundException();
    }

    /**
     * Detects all allowed methods of the given path.
     *
     * @param $path
     *
     * @return array
     */
    private function getAllowedMethods($path)
    {
        $pattern = $this->getCombinedPattern();

        $path = $this->formatPath($path);
        $allowedMethods = [];
        foreach (static::$methods as $method) {
            if (preg_match($pattern, $method . ' ' . $path)) {
                $allowedMethods[] = $method;
            }
        }

        return $allowedMethods;
    }

    /**
     * Get the combined pattern.
     *
     * @return null|string returns null if no patterns, otherwise,
     * combines all patterns into one, and returns the combined pattern.
     */
    private function getCombinedPattern()
    {
        if ($this->combinedPattern === null) {
            if (empty($this->patterns)) {
                return null;
            }
            $this->combinedPattern = "#^(?:" . implode("|", $this->patterns) . ")$#x";
        }

        return $this->combinedPattern;
    }

    private function formatPath($path)
    {
        if ($path == '') {
            return '/';
        }

        if ($path[0] != '/') {
            return '/' . $path;
        }

        return $path;
    }
}