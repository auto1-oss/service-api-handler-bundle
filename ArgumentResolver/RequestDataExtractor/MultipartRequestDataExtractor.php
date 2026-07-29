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

namespace Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Multipart\UploadedFileStream;
use LogicException;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_merge;
use function is_array;
use function sprintf;
use function str_starts_with;

class MultipartRequestDataExtractor implements RequestDataExtractorInterface
{
    private const CONTENT_TYPE = 'multipart/form-data';

    private ?StreamFactoryInterface $streamFactory;

    public function __construct(?StreamFactoryInterface $streamFactory = null)
    {
        $this->streamFactory = $streamFactory;
    }

    public function supports(EndpointInterface $endpoint): bool
    {
        return EndpointInterface::FORMAT_MULTIPART === $endpoint->getRequestFormat();
    }

    public function extract(Request $request, EndpointInterface $endpoint): array
    {
        // PHP populates $_POST / $_FILES for POST requests only; for any other method the
        // multipart body would be silently ignored and the payload would come out empty.
        if (!$request->isMethod(Request::METHOD_POST)) {
            throw new BadRequestHttpException(
                sprintf(
                    'multipart/form-data endpoints only support POST, got "%s".',
                    $request->getMethod()
                )
            );
        }

        $contentType = (string) $request->headers->get('CONTENT_TYPE');
        if (!str_starts_with($contentType, self::CONTENT_TYPE)) {
            throw new BadRequestHttpException(
                sprintf(
                    'Expected "%s" content type, got "%s".',
                    self::CONTENT_TYPE,
                    $contentType
                )
            );
        }

        return array_merge(
            $request->request->all(),
            $this->wrapFiles($request->files->all()),
            $request->attributes->all(),
            $request->query->all()
        );
    }

    private function wrapFiles(array $files): array
    {
        $wrapped = [];
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $wrapped[$key] = $this->wrapFiles($value);
                continue;
            }

            // An unfilled optional file input arrives as null and is intentionally
            // omitted, so the request DTO keeps its default value.
            if (!$value instanceof UploadedFile) {
                continue;
            }

            if (!$value->isValid()) {
                throw new BadRequestHttpException(
                    sprintf('Upload failed for field "%s": %s', $key, $value->getErrorMessage())
                );
            }

            if (null === $this->streamFactory) {
                throw new LogicException(
                    sprintf(
                        'A PSR-17 "%s" must be wired to handle multipart/form-data file uploads. '
                        . 'Install a PSR-7 implementation (e.g. guzzlehttp/psr7, nyholm/psr7) '
                        . 'and register its stream factory.',
                        StreamFactoryInterface::class
                    )
                );
            }

            $stream = $this->streamFactory->createStreamFromFile($value->getPathname(), 'r');
            $wrapped[$key] = new UploadedFileStream($stream, $value);
        }

        return $wrapped;
    }
}