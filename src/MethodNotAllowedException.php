<?php
namespace RazonYang\Routing;

class MethodNotAllowedException extends \Exception
{
    public function __construct($message = "Method Not Allowed", $code = 405, \Throwable $previous = null)
    {
    }
}