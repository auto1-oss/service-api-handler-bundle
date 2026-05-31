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

use function array_merge;

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

    public function extract(Request $request, EndpointInterface $endpoint): array
    {
        $body = $request->getContent();
        $decoded = !empty($body)
            ? $this->decoder->decode($body, $endpoint->getRequestFormat())
            : [];

        return array_merge(
            $decoded,
            $request->attributes->all(),
            $request->query->all()
        );
    }
}
