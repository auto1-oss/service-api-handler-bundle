<?php

namespace Auto1\ServiceAPIHandlerBundle\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * Class ServiceResponse
 */
class ServiceResponse extends ResponseDelegator
{
    /**
     * @var mixed
     */
    private $data;

    /**
     * ServiceResponse constructor.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     */
    public function __construct($data = '', int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}
