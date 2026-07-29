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

namespace Tests\Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\Endpoint;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointProviderInterface;

class EndpointProviderStub implements EndpointProviderInterface
{
    private string $requestClass;
    private string $requestFormat;

    public function __construct(string $requestClass, string $requestFormat)
    {
        $this->requestClass = $requestClass;
        $this->requestFormat = $requestFormat;
    }

    public function getEndpoints(): array
    {
        $endpoint = new Endpoint();
        $endpoint->setRequestClass($this->requestClass);
        $endpoint->setRequestFormat($this->requestFormat);

        return [$endpoint];
    }
}
