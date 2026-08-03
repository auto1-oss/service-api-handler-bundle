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
use function explode;
use function in_array;
use function is_array;
use function sprintf;
use function strtolower;
use function trim;

class MultipartRequestDataExtractor implements RequestDataExtractorInterface
{
    private const MIME_TYPE_MULTIPART = 'multipart/form-data';

    // Request::create()/BrowserKit default POST bodies to urlencoded even when files are
    // attached, and PHP parses both form mime types into the same superglobals — accept either.
    private const FORM_MIME_TYPES = [
        self::MIME_TYPE_MULTIPART,
        'application/x-www-form-urlencoded',
    ];

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
        // PHP populates $_POST / $_FILES only when the wire method is POST; method overrides
        // (_method / X-HTTP-METHOD-OVERRIDE) are applied after parsing, so the real method
        // decides whether the body was parsed — an overridden wire POST is fine.
        if (Request::METHOD_POST !== $request->getRealMethod()) {
            throw new BadRequestHttpException(
                sprintf(
                    'multipart/form-data endpoints must be sent as POST, got "%s".',
                    $request->getRealMethod()
                )
            );
        }

        // Media types are case-insensitive (RFC 9110) and may carry parameters (boundary).
        $contentType = (string) $request->headers->get('CONTENT_TYPE');
        $mimeTypeParts = explode(';', $contentType, 2);
        $mimeType = strtolower(trim($mimeTypeParts[0]));
        if (!in_array($mimeType, self::FORM_MIME_TYPES, true)) {
            throw new BadRequestHttpException(
                sprintf(
                    'Expected "%s" content type, got "%s".',
                    self::MIME_TYPE_MULTIPART,
                    $contentType
                )
            );
        }

        // A body PHP failed to parse (e.g. post_max_size exceeded) leaves both bags empty
        // while the body itself is non-empty — reject it instead of letting the request
        // through with a silently empty payload.
        if (0 === $request->request->count()
            && 0 === $request->files->count()
            && 0 < (int) $request->headers->get('CONTENT_LENGTH')
        ) {
            throw new BadRequestHttpException(
                'Form body could not be parsed — the "post_max_size" limit may be exceeded.'
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