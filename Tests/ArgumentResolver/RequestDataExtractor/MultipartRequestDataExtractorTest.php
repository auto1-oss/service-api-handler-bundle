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
use Auto1\ServiceAPIComponentsBundle\Multipart\UploadedFileStream;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\MultipartRequestDataExtractor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MultipartRequestDataExtractorTest extends TestCase
{
    private const TARGET_FORMAT = 'multipart';
    private const TARGET_TMP_PREFIX = 'multipart-test-';
    private const TARGET_POST_SERVER = [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'multipart/form-data; boundary=test',
    ];

    /**
     * @var (StreamFactoryInterface&MockObject)|null
     */
    private ?StreamFactoryInterface $streamFactory;

    private Endpoint $endpoint;

    /**
     * @var string[]
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->endpoint = new Endpoint();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
        $this->tmpFiles = [];
    }

    private function getCut(): MultipartRequestDataExtractor
    {
        return new MultipartRequestDataExtractor($this->streamFactory);
    }

    private function createTmpFile(string $content): string
    {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, self::TARGET_TMP_PREFIX);
        file_put_contents($tmpFile, $content);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    public function testSupportsMultipartFormat(): void
    {
        $this->endpoint->setRequestFormat(self::TARGET_FORMAT);

        $target = $this->getCut();

        $result = $target->supports($this->endpoint);

        self::assertTrue($result);
    }

    public function testDoesNotSupportOtherFormats(): void
    {
        $targetOtherFormat = 'json';

        $this->endpoint->setRequestFormat($targetOtherFormat);

        $target = $this->getCut();

        $result = $target->supports($this->endpoint);

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

        $targetTmp = $this->createTmpFile($targetFileContent);
        $targetUploadedFile = new UploadedFile($targetTmp, $targetFileName, $targetMimeType, null, true);

        $request = new Request(
            $targetQuery,
            $targetTextFields,
            $targetAttributes,
            [],
            [$targetFileFieldKey => $targetUploadedFile],
            self::TARGET_POST_SERVER
        );

        $targetStreamReadMode = 'r';
        $targetStream = $this->createMock(StreamInterface::class);
        $this->streamFactory
            ->expects(self::once())
            ->method('createStreamFromFile')
            ->with($targetTmp, $targetStreamReadMode)
            ->willReturn($targetStream)
        ;

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertSame($targetTextFields['targetTextFieldKey'], $result['targetTextFieldKey']);
        self::assertSame($targetQuery['targetQueryKey'], $result['targetQueryKey']);
        self::assertSame($targetAttributes['targetAttributeKey'], $result['targetAttributeKey']);
        self::assertInstanceOf(UploadedFileStream::class, $result[$targetFileFieldKey]);
    }

    public function testExtractWrapsNestedFileArrays(): void
    {
        $targetFileName = 'doc.pdf';
        $targetMimeType = 'application/pdf';
        $targetFilesFieldKey = 'docs';
        $targetFileContent = 'content';

        $targetFirstTmp = $this->createTmpFile($targetFileContent);
        $targetSecondTmp = $this->createTmpFile($targetFileContent);
        $targetFirstFile = new UploadedFile($targetFirstTmp, $targetFileName, $targetMimeType, null, true);
        $targetSecondFile = new UploadedFile($targetSecondTmp, $targetFileName, $targetMimeType, null, true);

        $request = new Request(
            [],
            [],
            [],
            [],
            [$targetFilesFieldKey => [$targetFirstFile, $targetSecondFile]],
            self::TARGET_POST_SERVER
        );

        $targetStream = $this->createMock(StreamInterface::class);
        $this->streamFactory
            ->expects(self::exactly(2))
            ->method('createStreamFromFile')
            ->willReturn($targetStream)
        ;

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertCount(2, $result[$targetFilesFieldKey]);
        self::assertInstanceOf(UploadedFileStream::class, $result[$targetFilesFieldKey][0]);
        self::assertInstanceOf(UploadedFileStream::class, $result[$targetFilesFieldKey][1]);
    }

    public function testExtractOmitsUnfilledOptionalFileInput(): void
    {
        $targetFileFieldKey = 'optional';
        $targetNoFileUpload = [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];

        $request = new Request(
            [],
            [],
            [],
            [],
            [$targetFileFieldKey => $targetNoFileUpload],
            self::TARGET_POST_SERVER
        );

        $this->streamFactory
            ->expects(self::never())
            ->method('createStreamFromFile')
        ;

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertArrayNotHasKey($targetFileFieldKey, $result);
    }

    public function testExtractThrowsOnInvalidUpload(): void
    {
        $targetFileName = 'too-big.png';
        $targetFileFieldKey = 'attachment';
        $targetFileContent = 'partial';

        $targetTmp = $this->createTmpFile($targetFileContent);
        $targetUploadedFile = new UploadedFile($targetTmp, $targetFileName, null, UPLOAD_ERR_PARTIAL, true);

        $request = new Request(
            [],
            [],
            [],
            [],
            [$targetFileFieldKey => $targetUploadedFile],
            self::TARGET_POST_SERVER
        );

        $this->streamFactory
            ->expects(self::never())
            ->method('createStreamFromFile')
        ;

        $target = $this->getCut();

        $this->expectException(BadRequestHttpException::class);
        $target->extract($request, $this->endpoint);
    }

    public function testExtractThrowsWhenStreamFactoryMissingForFileUpload(): void
    {
        $targetFileName = 'avatar.png';
        $targetMimeType = 'image/png';
        $targetFileFieldKey = 'avatar';
        $targetFileContent = 'hello';

        $targetTmp = $this->createTmpFile($targetFileContent);
        $targetUploadedFile = new UploadedFile($targetTmp, $targetFileName, $targetMimeType, null, true);

        $this->streamFactory = null;
        $request = new Request(
            [],
            [],
            [],
            [],
            [$targetFileFieldKey => $targetUploadedFile],
            self::TARGET_POST_SERVER
        );

        $target = $this->getCut();

        $this->expectException(LogicException::class);
        $target->extract($request, $this->endpoint);
    }

    public function testExtractDoesNotRequireFactoryWhenNoFilesPresent(): void
    {
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];

        $this->streamFactory = null;
        $request = new Request(
            [],
            $targetTextFields,
            [],
            [],
            [],
            self::TARGET_POST_SERVER
        );

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertSame($targetTextFields, $result);
    }

    public function testExtractRejectsNonPostRequest(): void
    {
        $targetPutServer = [
            'REQUEST_METHOD' => 'PUT',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=test',
        ];

        $request = new Request([], [], [], [], [], $targetPutServer);

        $target = $this->getCut();

        $this->expectException(BadRequestHttpException::class);
        $target->extract($request, $this->endpoint);
    }

    public function testExtractRejectsNonMultipartContentType(): void
    {
        $targetJsonServer = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
        ];

        $request = new Request([], [], [], [], [], $targetJsonServer);

        $target = $this->getCut();

        $this->expectException(BadRequestHttpException::class);
        $target->extract($request, $this->endpoint);
    }

    public function testExtractAllowsMethodOverrideOnWirePostRequest(): void
    {
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];
        $targetOverriddenServer = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=test',
            'HTTP_X_HTTP_METHOD_OVERRIDE' => 'PUT',
        ];

        $request = new Request([], $targetTextFields, [], [], [], $targetOverriddenServer);

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertSame($targetTextFields, $result);
    }

    public function testExtractAcceptsCaseVariantContentType(): void
    {
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];
        $targetCaseVariantServer = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'Multipart/Form-Data; boundary=test',
        ];

        $request = new Request([], $targetTextFields, [], [], [], $targetCaseVariantServer);

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertSame($targetTextFields, $result);
    }

    public function testExtractAcceptsUrlEncodedContentType(): void
    {
        $targetTextFields = ['targetTextFieldKey' => 'targetTextFieldValue'];
        $targetUrlEncodedServer = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];

        $request = new Request([], $targetTextFields, [], [], [], $targetUrlEncodedServer);

        $target = $this->getCut();

        $result = $target->extract($request, $this->endpoint);

        self::assertSame($targetTextFields, $result);
    }

    public function testExtractRejectsUnparsedFormBody(): void
    {
        $targetUnparsedServer = [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=test',
            'CONTENT_LENGTH' => '1048576',
        ];

        $request = new Request([], [], [], [], [], $targetUnparsedServer);

        $target = $this->getCut();

        $this->expectException(BadRequestHttpException::class);
        $target->extract($request, $this->endpoint);
    }
}
