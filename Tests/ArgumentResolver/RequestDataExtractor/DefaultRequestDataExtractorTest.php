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

namespace Tests\Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\Endpoint;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\DefaultRequestDataExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class DefaultRequestDataExtractorTest extends TestCase
{
    private const TARGET_FORMAT = 'json';

    /**
     * @var DecoderInterface&MockObject
     */
    private DecoderInterface $decoder;

    private Endpoint $endpoint;

    protected function setUp(): void
    {
        $this->decoder = $this->createMock(DecoderInterface::class);
        $this->endpoint = new Endpoint();
    }

    private function getCut(): DefaultRequestDataExtractor
    {
        return new DefaultRequestDataExtractor($this->decoder);
    }

    public function testSupportsIsAlwaysTrue(): void
    {
        $extractor = $this->getCut();

        $result = $extractor->supports($this->endpoint);

        self::assertTrue($result);
    }

    public function testExtractMergesDecodedBodyAttributesAndQuery(): void
    {
        $targetBody = 'foobar';
        $targetQuery = ['targetQueryKey' => 'targetQueryValue'];
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];
        $targetDecoded = ['targetBodyKey' => 'targetBodyValue'];

        $request = new Request(
            $targetQuery,
            [],
            $targetAttributes,
            [],
            [],
            [],
            $targetBody
        );

        $this->endpoint->setRequestFormat(self::TARGET_FORMAT);

        $this->decoder
            ->expects(self::once())
            ->method('decode')
            ->with($targetBody, self::TARGET_FORMAT)
            ->willReturn($targetDecoded)
        ;

        $extractor = $this->getCut();

        $result = $extractor->extract($request, $this->endpoint);

        self::assertSame(
            array_merge($targetDecoded, $targetAttributes, $targetQuery),
            $result
        );
    }

    public function testExtractSkipsDecodeWhenBodyIsEmpty(): void
    {
        $targetQuery = ['targetQueryKey' => 'targetQueryValue'];
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];

        $request = new Request($targetQuery, [], $targetAttributes);

        $this->decoder
            ->expects(self::never())
            ->method('decode')
        ;

        $extractor = $this->getCut();

        $result = $extractor->extract($request, $this->endpoint);

        self::assertSame(array_merge($targetAttributes, $targetQuery), $result);
    }

    public function testExtractDecodesZeroStringBody(): void
    {
        $targetBody = '0';
        $targetDecoded = ['targetBodyKey' => 'targetBodyValue'];

        $request = new Request([], [], [], [], [], [], $targetBody);

        $this->endpoint->setRequestFormat(self::TARGET_FORMAT);

        $this->decoder
            ->expects(self::once())
            ->method('decode')
            ->with($targetBody, self::TARGET_FORMAT)
            ->willReturn($targetDecoded)
        ;

        $extractor = $this->getCut();

        $result = $extractor->extract($request, $this->endpoint);

        self::assertSame($targetDecoded, $result);
    }

    public function testExtractPropagatesDecodeException(): void
    {
        $targetBody = 'not-a-valid-payload';
        $targetException = new NotEncodableValueException();

        $request = new Request([], [], [], [], [], [], $targetBody);

        $this->endpoint->setRequestFormat(self::TARGET_FORMAT);

        $this->decoder
            ->method('decode')
            ->with($targetBody, self::TARGET_FORMAT)
            ->willThrowException($targetException)
        ;

        $extractor = $this->getCut();

        $this->expectException(NotEncodableValueException::class);
        $extractor->extract($request, $this->endpoint);
    }
}
