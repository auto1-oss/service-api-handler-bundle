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
use Auto1\ServiceAPIComponentsBundle\Multipart\UploadedFileStream;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\MultipartRequestDataExtractor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class MultipartRequestDataExtractorTest extends TestCase
{
    private const TARGET_FORMAT = 'multipart';

    /**
     * @var (StreamFactoryInterface&MockObject)|null
     */
    private ?StreamFactoryInterface $streamFactory;

    /**
     * @var EndpointInterface&MockObject
     */
    private EndpointInterface $endpoint;

    protected function setUp(): void
    {
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->endpoint = $this->createMock(EndpointInterface::class);
    }

    private function getCut(): MultipartRequestDataExtractor
    {
        return new MultipartRequestDataExtractor($this->streamFactory);
    }

    public function testSupportsMultipartFormat(): void
    {
        $this->endpoint
            ->method('getRequestFormat')
            ->willReturn(self::TARGET_FORMAT)
        ;

        $extractor = $this->getCut();

        $result = $extractor->supports($this->endpoint);

        self::assertTrue($result);
    }

    public function testDoesNotSupportOtherFormats(): void
    {
        $targetOtherFormat = 'json';

        $this->endpoint
            ->method('getRequestFormat')
            ->willReturn($targetOtherFormat)
        ;

        $extractor = $this->getCut();

        $result = $extractor->supports($this->endpoint);

        self::assertFalse($result);
    }

    public function testExtractMergesTextFieldsFilesAttributesAndQuery(): void
    {
        $targetFileName = 'avatar.png';
        $targetMimeType = 'image/png';
        $targetFileFieldKey = 'avatar';
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];
        $targetQuery = ['targetQueryKey' => 'targetQueryValue'];
        $targetAttributes = ['targetAttributeKey' => 'targetAttributeValue'];
        $targetFileContent = 'hello';

        $targetTmp = tempnam(sys_get_temp_dir(), 'multipart-test-');
        file_put_contents($targetTmp, $targetFileContent);
        $targetUploadedFile = new UploadedFile($targetTmp, $targetFileName, $targetMimeType, null, true);

        $request = new Request(
            $targetQuery,
            $targetTextFields,
            $targetAttributes,
            [],
            [$targetFileFieldKey => $targetUploadedFile],
            ['CONTENT_TYPE' => 'multipart/form-data; boundary=test']
        );

        $targetStream = $this->createMock(StreamInterface::class);
        $this->streamFactory
            ->expects(self::once())
            ->method('createStreamFromFile')
            ->with($targetTmp, 'r')
            ->willReturn($targetStream)
        ;

        $extractor = $this->getCut();

        $result = $extractor->extract($request, $this->endpoint);

        $this->assertSame($targetTextFields['targetTextFieldKey'], $result['targetTextFieldKey']);
        $this->assertSame($targetQuery['targetQueryKey'], $result['targetQueryKey']);
        $this->assertSame($targetAttributes['targetAttributeKey'], $result['targetAttributeKey']);
        $this->assertInstanceOf(UploadedFileStream::class, $result[$targetFileFieldKey]);

        unlink($targetTmp);
    }

    public function testExtractThrowsWhenStreamFactoryMissing(): void
    {
        $this->streamFactory = null;
        $request = new Request();

        $extractor = $this->getCut();

        $this->expectException(LogicException::class);
        $extractor->extract($request, $this->endpoint);
    }

    public function testExtractRequiresFactoryEvenWhenNoFilesPresent(): void
    {
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];

        $this->streamFactory = null;
        $request = new Request([], $targetTextFields);

        $extractor = $this->getCut();

        $this->expectException(LogicException::class);
        $extractor->extract($request, $this->endpoint);
    }
}
