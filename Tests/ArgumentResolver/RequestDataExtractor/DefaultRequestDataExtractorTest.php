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

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\DefaultRequestDataExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

class DefaultRequestDataExtractorTest extends TestCase
{
    private const TARGET_FORMAT = 'json';

    /**
     * @var DecoderInterface&MockObject
     */
    private DecoderInterface $decoder;

    /**
     * @var EndpointInterface&MockObject
     */
    private EndpointInterface $endpoint;

    protected function setUp(): void
    {
        $this->decoder = $this->createMock(DecoderInterface::class);
        $this->endpoint = $this->createMock(EndpointInterface::class);
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

        $this->endpoint
            ->method('getRequestFormat')
            ->willReturn(self::TARGET_FORMAT)
        ;

        $this->decoder
            ->expects($this->once())
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
}
