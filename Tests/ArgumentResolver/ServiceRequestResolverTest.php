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
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\ServiceRequestResolver;
use Auto1\ServiceAPIHandlerBundle\EventListener\ServiceResponseListener;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ServiceRequestResolverTest extends TestCase
{
    /**
     * @var SerializerInterface|DecoderInterface|DenormalizerInterface|ObjectProphecy
     */
    private $serializerProphecy;

    /**
     * @var EndpointRegistryInterface|ObjectProphecy
     */
    private $endpointRegistryProphecy;

    /**
     * @var ServiceResponseListener|ObjectProphecy
     */
    private $serviceResponseListenerProphecy;

    /**
     * @var ServiceRequestResolver
     */
    private $serviceRequestResolver;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->serializerProphecy = $this->prophesize(DecodeDenormalizeAwareSerializerInterface::class);
        $this->endpointRegistryProphecy = $this->prophesize(EndpointRegistryInterface::class);
        $this->serviceResponseListenerProphecy = $this->prophesize(ServiceResponseListener::class);
        $this->serviceRequestResolver = new ServiceRequestResolver(
            $this->serializerProphecy->reveal(),
            $this->endpointRegistryProphecy->reveal(),
            $this->serviceResponseListenerProphecy->reveal()
        );
    }

    /**
     * @return void
     */
    public function testResolveWrongRequestClass(): void
    {
        $generator = $this->serviceRequestResolver->resolve(
            new Request(),
            $this->createMetadata()
        );
        $endpointProphecy = $this->prophesize(EndpointInterface::class);
        $this->endpointRegistryProphecy->getEndpoint(Argument::type(RequestStub::class))
            ->willReturn($endpointProphecy->reveal())
            ->shouldBeCalled();
        $endpointProphecy->getRequestClass()
            ->willReturn(\stdClass::class)
            ->shouldBeCalled();

        $this->expectException(\LogicException::class);
        $generator->current();
    }

    /**
     * @return void
     */
    public function testResolveDecodeException(): void
    {
        $generator = $this->serviceRequestResolver->resolve(
            new Request([], [], ['baz' => 'qux'], [], [], [], 'foobar'),
            $this->createMetadata()
        );
        $endpointProphecy = $this->prophesize(EndpointInterface::class);
        $this->endpointRegistryProphecy->getEndpoint(Argument::type(RequestStub::class))
            ->willReturn($endpointProphecy->reveal())
            ->shouldBeCalled();
        $endpointProphecy->getRequestClass()
            ->willReturn(RequestStub::class)
            ->shouldBeCalled();
        $endpointProphecy->getRequestFormat()
            ->willReturn('json')
            ->shouldBeCalled();
        $this->serializerProphecy->decode('foobar', 'json', Argument::cetera())
            ->willThrow(UnexpectedValueException::class)
            ->shouldBeCalled();

        $this->expectException(BadRequestHttpException::class);
        $generator->current();
    }

    /**
     * @return void
     */
    public function testResolveDeserializationException()
    {
        $generator = $this->serviceRequestResolver->resolve(
            new Request([], [], ['baz' => 'qux'], [], [], [], 'foobar'),
            $this->createMetadata()
        );
        $endpointProphecy = $this->prophesize(EndpointInterface::class);
        $this->endpointRegistryProphecy->getEndpoint(Argument::type(RequestStub::class))
            ->willReturn($endpointProphecy->reveal())
            ->shouldBeCalled();
        $endpointProphecy->getRequestClass()
            ->willReturn(RequestStub::class)
            ->shouldBeCalled();
        $endpointProphecy->getRequestFormat()
            ->willReturn('json')
            ->shouldBeCalled();
        $this->serializerProphecy->decode('foobar', 'json', Argument::cetera())
            ->willReturn(['foo' => 'bar'])
            ->shouldBeCalled();
        $this->serializerProphecy->denormalize(
            ['foo' => 'bar', 'baz' => 'qux'],
            RequestStub::class,
            'json',
            Argument::cetera()
        )
            ->willThrow(NotNormalizableValueException::class)
            ->shouldBeCalled();

        $this->expectException(BadRequestHttpException::class);
        $generator->current();
    }

    public function testResolve(): void
    {
        $generator = $this->serviceRequestResolver->resolve(
            new Request([], [], ['baz' => 'qux'], [], [], [], 'foobar'),
            $this->createMetadata()
        );
        $endpointProphecy = $this->prophesize(EndpointInterface::class);
        $this->endpointRegistryProphecy->getEndpoint(Argument::type(RequestStub::class))
            ->willReturn($endpointProphecy->reveal())
            ->shouldBeCalled();
        $endpointProphecy->getRequestClass()
            ->willReturn(RequestStub::class)
            ->shouldBeCalled();
        $endpointProphecy->getRequestFormat()
            ->willReturn('json')
            ->shouldBeCalled();
        $this->serializerProphecy->decode('foobar', 'json', Argument::cetera())
            ->willReturn(['foo' => 'bar'])
            ->shouldBeCalled();
        $this->serializerProphecy->denormalize(
            ['foo' => 'bar', 'baz' => 'qux'],
            RequestStub::class,
            'json',
            Argument::cetera()
        )
            ->willReturn(new RequestStub())
            ->shouldBeCalled();

        $this->assertInstanceOf(RequestStub::class, $generator->current());
    }

    /**
     * @dataProvider getDataForTestSupports
     * @param ArgumentMetadata $argumentMetadata
     * @param bool             $expectedResult
     * @return void
     */
    public function testSupports(ArgumentMetadata $argumentMetadata, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->serviceRequestResolver->supports(new Request(), $argumentMetadata));
    }

    public static function getDataForTestSupports(): \Generator
    {
        yield 'supported' => [self::createMetadata(), true];
        yield 'not supported' => [new ArgumentMetadata('bar', \stdClass::class, false, false, null), false];
    }

    private static function createMetadata(): ArgumentMetadata
    {
        return new ArgumentMetadata('foo', RequestStub::class, false, false, null);
    }
}
