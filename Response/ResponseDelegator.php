<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\ServiceAPIHandlerBundle\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * Class ResponseDelegator
 *
 * @mixin Response
 */
class ResponseDelegator
{
    /**
     * @var Response
     */
    protected $response;

    /**
     * ResponseDelegator constructor.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     */
    public function __construct($content = '', int $status = 200, array $headers = [])
    {
        $this->response = new Response($content, $status, $headers);
    }

    /**
     * Delegating
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->response->{$name}(...$arguments);
    }

    /**
     * Method for granting *read* access to public properties like: Response->headers
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->response->{$name};
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->response->{$name});
    }

    /**
     * Any *write* to property goes to /dev/null
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
    }

    public function __clone()
    {
        $this->response = clone $this->response;
    }

    public static function __callStatic($name, $arguments)
    {
        throw new \RuntimeException(sprintf(
           'Static method %1$s::%3$s is not supported, please use %2$s::%3$s instead',
           static::class,
           Response::class,
           $name
        ));
    }
}
