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

namespace Tests\Auto1\ServiceAPIHandlerBundle\ArgumentResolver;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\Endpoint;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\RequestDataExtractorInterface;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\ServiceRequestResolver;
use Auto1\ServiceAPIHandlerBundle\EventListener\ServiceResponseListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ServiceRequestResolverTest extends TestCase
{
    private const TARGET_FORMAT = 'json';
    private const TARGET_MULTIPART_FORMAT = 'multipart';

    /** @var DenormalizerInterface&MockObject */
    private DenormalizerInterface $denormalizer;

    /** @var EndpointRegistryInterface&MockObject */
    private EndpointRegistryInterface $endpointRegistry;

    /** @var ServiceResponseListener&MockObject */
    private ServiceResponseListener $serviceResponseListener;

    /** @var RequestDataExtractorInterface&MockObject */
    private RequestDataExtractorInterface $extractor;

    /** @var iterable<RequestDataExtractorInterface> */
    private iterable $extractors;

    protected function setUp(): void
    {
        $this->denormalizer = $this->createMock(DenormalizerInterface::class);
        $this->endpointRegistry = $this->createMock(EndpointRegistryInterface::class);
        $this->serviceResponseListener = $this->createMock(ServiceResponseListener::class);
        $this->extractor = $this->createMock(RequestDataExtractorInterface::class);
        $this->extractors = [$this->extractor];
    }

    private function getCut(): ServiceRequestResolver
    {
        return new ServiceRequestResolver(
            $this->denormalizer,
            $this->endpointRegistry,
            $this->serviceResponseListener,
            $this->extractors
        );
    }

    private function createMetadata(): ArgumentMetadata
    {
        return new ArgumentMetadata('foo', RequestStub::class, false, false, null);
    }

    private function createEndpoint(string $requestClass, string $requestFormat): Endpoint
    {
        $endpoint = new Endpoint();
        $endpoint->setRequestClass($requestClass);
        $endpoint->setRequestFormat($requestFormat);

        return $endpoint;
    }

    public function testResolveWrongRequestClass(): void
    {
        $endpoint = $this->createEndpoint(\stdClass::class, self::TARGET_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);

        $request = new Request();
        $metadata = $this->createMetadata();

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $this->expectException(\LogicException::class);
        $generator->current();
    }

    public function testResolveNoSupportingExtractor(): void
    {
        $endpoint = $this->createEndpoint(RequestStub::class, self::TARGET_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $this->extractor->method('supports')->with($endpoint)->willReturn(false);

        $request = new Request();
        $metadata = $this->createMetadata();

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $this->expectException(\LogicException::class);
        $generator->current();
    }

    public function testResolveDeserializationException(): void
    {
        $targetBody = 'foobar';
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];
        $targetExtracted = ['targetBodyKey' => 'targetBodyValue', 'targetAttributeKey' => 'targetAttributeValue'];
        $targetException = new NotNormalizableValueException();

        $request = new Request([], [], $targetAttributes, [], [], [], $targetBody);
        $metadata = $this->createMetadata();

        $endpoint = $this->createEndpoint(RequestStub::class, self::TARGET_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $this->extractor->method('supports')->with($endpoint)->willReturn(true);
        $this->extractor->method('extract')->with($request, $endpoint)->willReturn($targetExtracted);
        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_FORMAT)
            ->willThrowException($targetException);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $this->expectException(BadRequestHttpException::class);
        $generator->current();
    }

    public function testResolveDecodeException(): void
    {
        $targetBody = 'not-a-valid-payload';
        $targetException = new NotEncodableValueException();

        $request = new Request([], [], [], [], [], [], $targetBody);
        $metadata = $this->createMetadata();

        $endpoint = $this->createEndpoint(RequestStub::class, self::TARGET_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $this->extractor->method('supports')->with($endpoint)->willReturn(true);
        $this->extractor
            ->method('extract')
            ->with($request, $endpoint)
            ->willThrowException($targetException);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $this->expectException(BadRequestHttpException::class);
        $generator->current();
    }

    public function testResolve(): void
    {
        $targetBody = 'foobar';
        $targetQuery = ['targetQueryKey' => 'targetQueryValue'];
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];
        $targetExtracted = [
            'targetBodyKey' => 'targetBodyValue',
            'targetAttributeKey' => 'targetAttributeValue',
            'targetQueryKey' => 'targetQueryValue',
        ];
        $targetDenormalized = new RequestStub();

        $request = new Request($targetQuery, [], $targetAttributes, [], [], [], $targetBody);
        $metadata = $this->createMetadata();

        $endpoint = $this->createEndpoint(RequestStub::class, self::TARGET_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $this->extractor->method('supports')->with($endpoint)->willReturn(true);
        $this->extractor->method('extract')->with($request, $endpoint)->willReturn($targetExtracted);
        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_FORMAT)
            ->willReturn($targetDenormalized);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $result = $generator->current();

        self::assertSame($targetDenormalized, $result);
    }

    public function testResolvePicksFirstSupportingExtractor(): void
    {
        $targetExtracted = ['targetKey' => 'targetValue'];
        $targetDenormalized = new RequestStub();

        $skippedExtractor = $this->createMock(RequestDataExtractorInterface::class);
        $matchingExtractor = $this->createMock(RequestDataExtractorInterface::class);
        $this->extractors = [$skippedExtractor, $matchingExtractor];

        $request = new Request();
        $metadata = $this->createMetadata();

        $endpoint = $this->createEndpoint(RequestStub::class, self::TARGET_MULTIPART_FORMAT);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);

        $skippedExtractor->expects(self::once())->method('supports')->with($endpoint)->willReturn(false);
        $skippedExtractor->expects(self::never())->method('extract');

        $matchingExtractor->expects(self::once())->method('supports')->with($endpoint)->willReturn(true);
        $matchingExtractor
            ->expects(self::once())
            ->method('extract')
            ->with($request, $endpoint)
            ->willReturn($targetExtracted);

        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_MULTIPART_FORMAT)
            ->willReturn($targetDenormalized);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $metadata);

        $result = $generator->current();

        self::assertSame($targetDenormalized, $result);
    }
}
