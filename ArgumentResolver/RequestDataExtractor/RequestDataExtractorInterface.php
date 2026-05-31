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

interface RequestDataExtractorInterface
{
    public function supports(EndpointInterface $endpoint): bool;

    /**
     * Returns the merged payload array to be passed to the serializer's denormalize() call
     * for the endpoint's request class.
     */
    public function extract(Request $request, EndpointInterface $endpoint): array;
}