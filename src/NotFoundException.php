<?php
namespace RazonYang\Routing;

class NotFoundException extends \Exception
{
    public function __construct($message = 'Not Found', $code = 404, \Throwable $previous = null)
    {
    }
}