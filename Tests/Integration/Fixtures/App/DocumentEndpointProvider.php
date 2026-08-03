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

namespace Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\Endpoint;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class DocumentEndpointProvider implements EndpointProviderInterface
{
    public function getEndpoints(): array
    {
        $endpoint = new Endpoint();
        $endpoint->setMethod(Request::METHOD_POST);
        $endpoint->setPath('/v1/document');
        $endpoint->setRequestFormat(EndpointInterface::FORMAT_MULTIPART);
        $endpoint->setRequestClass(UploadDocumentRequest::class);
        $endpoint->setResponseFormat(EndpointInterface::FORMAT_JSON);

        return [$endpoint];
    }
}
