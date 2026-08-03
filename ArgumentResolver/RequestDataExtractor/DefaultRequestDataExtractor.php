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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

use function array_merge;
use function get_debug_type;
use function is_array;
use function sprintf;

class DefaultRequestDataExtractor implements RequestDataExtractorInterface
{
    private DecoderInterface $decoder;

    public function __construct(DecoderInterface $decoder)
    {
        $this->decoder = $decoder;
    }

    public function supports(EndpointInterface $endpoint): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(Request $request, EndpointInterface $endpoint): array
    {
        $decoded = [];

        $body = $request->getContent();
        if ('' !== $body) {
            $decoded = $this->decoder->decode($body, $endpoint->getRequestFormat());

            // A scalar body (e.g. JSON `0` or `"foo"`) decodes without error but cannot feed
            // array_merge(); UnexpectedValueException is a Serializer ExceptionInterface, so
            // ServiceRequestResolver converts it to a 400 instead of a TypeError-driven 500.
            if (!is_array($decoded)) {
                throw new UnexpectedValueException(
                    sprintf('Request body must decode to an array, got "%s".', get_debug_type($decoded))
                );
            }
        }

        return array_merge(
            $decoded,
            $request->attributes->all(),
            $request->query->all()
        );
    }
}
