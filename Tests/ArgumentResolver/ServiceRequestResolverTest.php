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

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\RequestDataExtractorInterface;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\ServiceRequestResolver;
use Auto1\ServiceAPIHandlerBundle\EventListener\ServiceResponseListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    public function testResolveWrongRequestClass(): void
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $endpoint->method('getRequestClass')->willReturn(\stdClass::class);

        $cut = $this->getCut();

        $generator = $cut->resolve(new Request(), $this->createMetadata());

        $this->expectException(\LogicException::class);
        $generator->current();
    }

    public function testResolveNoSupportingExtractor(): void
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $endpoint->method('getRequestClass')->willReturn(RequestStub::class);
        $endpoint->method('getRequestFormat')->willReturn(self::TARGET_FORMAT);
        $this->extractor->method('supports')->with($endpoint)->willReturn(false);

        $cut = $this->getCut();

        $generator = $cut->resolve(new Request(), $this->createMetadata());

        $this->expectException(\LogicException::class);
        $generator->current();
    }

    public function testResolveDeserializationException(): void
    {
        $targetBody = 'foobar';
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];
        $targetExtracted = ['targetBodyKey' => 'targetBodyValue', 'targetAttributeKey' => 'targetAttributeValue'];

        $request = new Request([], [], $targetAttributes, [], [], [], $targetBody);

        $endpoint = $this->createMock(EndpointInterface::class);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $endpoint->method('getRequestClass')->willReturn(RequestStub::class);
        $endpoint->method('getRequestFormat')->willReturn(self::TARGET_FORMAT);
        $this->extractor->method('supports')->with($endpoint)->willReturn(true);
        $this->extractor->method('extract')->with($request, $endpoint)->willReturn($targetExtracted);
        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_FORMAT)
            ->willThrowException(new NotNormalizableValueException());

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $this->createMetadata());

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

        $endpoint = $this->createMock(EndpointInterface::class);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $endpoint->method('getRequestClass')->willReturn(RequestStub::class);
        $endpoint->method('getRequestFormat')->willReturn(self::TARGET_FORMAT);
        $this->extractor->method('supports')->with($endpoint)->willReturn(true);
        $this->extractor->method('extract')->with($request, $endpoint)->willReturn($targetExtracted);
        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_FORMAT)
            ->willReturn($targetDenormalized);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $this->createMetadata());

        $this->assertSame($targetDenormalized, $generator->current());
    }

    public function testResolvePicksFirstSupportingExtractor(): void
    {
        $targetExtracted = ['targetKey' => 'targetValue'];
        $targetDenormalized = new RequestStub();

        $skippedExtractor = $this->createMock(RequestDataExtractorInterface::class);
        $matchingExtractor = $this->createMock(RequestDataExtractorInterface::class);
        $this->extractors = [$skippedExtractor, $matchingExtractor];

        $request = new Request();
        $endpoint = $this->createMock(EndpointInterface::class);
        $this->endpointRegistry->method('getEndpoint')->willReturn($endpoint);
        $endpoint->method('getRequestClass')->willReturn(RequestStub::class);
        $endpoint->method('getRequestFormat')->willReturn(self::TARGET_MULTIPART_FORMAT);

        $skippedExtractor->expects($this->once())->method('supports')->with($endpoint)->willReturn(false);
        $skippedExtractor->expects($this->never())->method('extract');

        $matchingExtractor->expects($this->once())->method('supports')->with($endpoint)->willReturn(true);
        $matchingExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($request, $endpoint)
            ->willReturn($targetExtracted);

        $this->denormalizer
            ->method('denormalize')
            ->with($targetExtracted, RequestStub::class, self::TARGET_MULTIPART_FORMAT)
            ->willReturn($targetDenormalized);

        $cut = $this->getCut();

        $generator = $cut->resolve($request, $this->createMetadata());

        $this->assertSame($targetDenormalized, $generator->current());
    }
}