<?php

namespace Auto1\ServiceAPIHandlerBundle\EventListener;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIHandlerBundle\Response\ServiceResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ServiceResponseListener
 */
class ServiceResponseListener implements EventSubscriberInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EndpointInterface[]
     */
    private $expectedRequests = [];

    /**
     * ServiceResponseListener constructor.
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    /**
     * Do the conversion if applicable and update the response of the event.
     *
     * @param GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $serviceResponse = $event->getControllerResult();

        if (!$serviceResponse instanceof ServiceResponse) {
            return;
        }

        $endpoint = $this->getExpectedRequestEndpoint($event->getRequest());

        $response = $this->buildResponse($serviceResponse, $endpoint);

        $event->setResponse($response);
    }

    /**
     * @param Request $request
     * @param EndpointInterface $endpoint
     */
    public function addExpectedRequestEndpoint(Request $request, EndpointInterface $endpoint)
    {
        $key = $this->getRequestIdentifier($request);

        if (array_key_exists($key, $this->expectedRequests)) {
            throw new \RuntimeException(
                sprintf(
                    '%s can not be mapped to multiple %s\'s',
                    Request::class,
                    EndpointInterface::class
                )
            );
        }

        $this->expectedRequests[$key] = $endpoint;
    }

    /**
     * @param Request $request
     * @return EndpointInterface
     */
    private function getExpectedRequestEndpoint(Request $request): EndpointInterface
    {
        return $this->expectedRequests[$this->getRequestIdentifier($request)];
    }

    /**
     * @param ServiceResponse $serviceResponse
     * @param EndpointInterface $endpoint
     *
     * @return Response
     */
    private function buildResponse(ServiceResponse $serviceResponse, EndpointInterface $endpoint): Response
    {
        if ($endpoint->getResponseClass() === null) {
            return $serviceResponse->getResponse();
        }

        $response = $serviceResponse->getResponse();

        if (is_string($serviceResponse->getData())) {
            $response->setContent($serviceResponse->getData());
        } else {
            $responseBody = $this->serializer->serialize(
                $serviceResponse->getData(),
                $endpoint->getResponseFormat(),
                [
                    DateTimeNormalizer::FORMAT_KEY => $endpoint->getDateTimeFormat()
                ]
            );

            $response->setContent($responseBody);
        }

        if (!$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', sprintf('application/%s', $endpoint->getResponseFormat()));
        }

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private function getRequestIdentifier(Request $request): string
    {
        return spl_object_hash($request);
    }
}
