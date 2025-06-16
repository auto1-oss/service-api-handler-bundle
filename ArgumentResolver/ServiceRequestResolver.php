<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\ServiceAPIHandlerBundle\ArgumentResolver;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Logger\LoggerAwareTrait;
use Auto1\ServiceAPIHandlerBundle\EventListener\ServiceResponseListener;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ArgumentResolver
 */
class ServiceRequestResolver implements ArgumentValueResolverInterface
{
    use LoggerAwareTrait;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EndpointRegistryInterface
     */
    private $endpointRegistry;

    /**
     * @var ServiceResponseListener
     */
    private $serviceResponseListener;

    /**
     * ServiceRequestResolver constructor.
     *
     * @param SerializerInterface $serializer
     * @param EndpointRegistryInterface $endpointRegistry
     * @param ServiceResponseListener $serviceResponseListener
     */
    public function __construct(
        SerializerInterface $serializer,
        EndpointRegistryInterface $endpointRegistry,
        ServiceResponseListener $serviceResponseListener
    ) {
        $this->serializer = $serializer;
        $this->endpointRegistry = $endpointRegistry;
        $this->serviceResponseListener = $serviceResponseListener;
    }

    /**
      * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return is_subclass_of($argument->getType(), ServiceRequestInterface::class, true);
    }

    /**
      * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $endpoint = $this->endpointRegistry->getEndpoint(
            (new \ReflectionClass($argument->getType()))->newInstanceWithoutConstructor()
        );

        if ($endpoint->getRequestClass() !== $argument->getType()) {
            throw new \LogicException('Incorrect resolving');
        }

        try {
            $requestVars = array_merge(
                !empty($request->getContent())
                    ? $this->serializer->decode(
                    $request->getContent(),
                    $endpoint->getRequestFormat()
                )
                    : []
                ,
                $request->attributes->all(),
                $request->query->all()
            );

            $this->serviceResponseListener->addExpectedRequestEndpoint($request, $endpoint);

            yield $this->serializer->denormalize(
                $requestVars,
                $endpoint->getRequestClass(),
                $endpoint->getRequestFormat(),
                [
                    AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ]
            );
        } catch (ExceptionInterface $exception) {
            $this->getLogger()->warning(
                'Request deserialization exception',
                [
                    'exception_message' => $exception->getMessage(),
                ]
            );
            throw new BadRequestHttpException('Request deserialization error');
        }
    }
}
