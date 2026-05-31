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

use function array_merge;
use function is_array;
use function sprintf;

class MultipartRequestDataExtractor implements RequestDataExtractorInterface
{
    public const FORMAT = 'multipart';

    private ?StreamFactoryInterface $streamFactory;

    public function __construct(?StreamFactoryInterface $streamFactory = null)
    {
        $this->streamFactory = $streamFactory;
    }

    public function supports(EndpointInterface $endpoint): bool
    {
        return self::FORMAT === $endpoint->getRequestFormat();
    }

    public function extract(Request $request, EndpointInterface $endpoint): array
    {
        if (null === $this->streamFactory) {
            throw new LogicException(
                sprintf(
                    'A PSR-17 "%s" must be wired to handle multipart/form-data endpoints. '
                    . 'Install a PSR-7 implementation (e.g. guzzlehttp/psr7, nyholm/psr7) '
                    . 'and register its stream factory.',
                    StreamFactoryInterface::class
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

            if ($value instanceof UploadedFile) {
                $stream = $this->streamFactory->createStreamFromFile($value->getRealPath(), 'r');
                $wrapped[$key] = new UploadedFileStream($stream, $value);
            }
        }

        return $wrapped;
    }
}
