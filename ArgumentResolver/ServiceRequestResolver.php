<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Auto1\ServiceAPIHandlerBundle\ArgumentResolver;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Logger\LoggerAwareTrait;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\RequestDataExtractorInterface;
use Auto1\ServiceAPIHandlerBundle\EventListener\ServiceResponseListener;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;
use LogicException;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use function sprintf;

/**
 * Class ArgumentResolver
 */
class ServiceRequestResolver implements ValueResolverInterface
{
    use LoggerAwareTrait;

    private DenormalizerInterface $denormalizer;
    private EndpointRegistryInterface $endpointRegistry;
    private ServiceResponseListener $serviceResponseListener;

    /**
     * @var iterable<RequestDataExtractorInterface>
     */
    private iterable $requestDataExtractors;

    /**
     * @param iterable<RequestDataExtractorInterface> $requestDataExtractors
     */
    public function __construct(
        DenormalizerInterface $denormalizer,
        EndpointRegistryInterface $endpointRegistry,
        ServiceResponseListener $serviceResponseListener,
        iterable $requestDataExtractors
    ) {
        $this->denormalizer = $denormalizer;
        $this->endpointRegistry = $endpointRegistry;
        $this->serviceResponseListener = $serviceResponseListener;
        $this->requestDataExtractors = $requestDataExtractors;
    }

    /**
     * @return iterable<mixed>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($argument)) {
            return [];
        }

        $endpoint = $this->endpointRegistry
            ->getEndpoint(
                (new ReflectionClass($argument->getType()))
                    ->newInstanceWithoutConstructor()
            )
        ;

        if ($endpoint->getRequestClass() !== $argument->getType()) {
            throw new LogicException('Incorrect resolving');
        }

        try {
            $requestVars = $this->resolveExtractor($endpoint)->extract($request, $endpoint);

            $this->serviceResponseListener->addExpectedRequestEndpoint($request, $endpoint);

            yield $this->denormalizer
                ->denormalize(
                    $requestVars,
                    $endpoint->getRequestClass(),
                    $endpoint->getRequestFormat(),
                    [
                        AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                    ]
                )
            ;
        } catch (ExceptionInterface $exception) {
            $this->getLogger()
                ->warning(
                    sprintf(
                        'Request deserialization exception: %s',
                        $exception->getMessage()
                    ),
                    [
                        'exception' => $exception,
                    ]
                )
            ;

            throw new BadRequestHttpException('Request deserialization error');
        }
    }

    private function resolveExtractor(EndpointInterface $endpoint): RequestDataExtractorInterface
    {
        foreach ($this->requestDataExtractors as $extractor) {
            if ($extractor->supports($endpoint)) {
                return $extractor;
            }
        }

        throw new LogicException(
            sprintf(
                'No request data extractor supports endpoint with format "%s".',
                $endpoint->getRequestFormat()
            )
        );
    }

    private function supports(ArgumentMetadata $argument): bool
    {
        return is_subclass_of($argument->getType(), ServiceRequestInterface::class, true);
    }
}
