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

namespace Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass;

use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointProviderInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function implode;
use function in_array;
use function sprintf;

/**
 * Fails container compilation when a controller-handled multipart endpoint is registered
 * but no PSR-17 stream factory is available — so the misconfiguration surfaces on deploy
 * instead of on the first upload request.
 */
class MultipartStreamFactoryCompilerPass implements CompilerPassInterface
{
    private const ENDPOINT_PROVIDER_TAG = 'auto1.api.endpoint_provider';
    private const CONTROLLER_MAPPING_PARAMETER = 'auto1.api_handler.controller_request_mapping';

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(StreamFactoryInterface::class)) {
            return;
        }

        if (!$container->hasParameter(self::CONTROLLER_MAPPING_PARAMETER)) {
            return;
        }

        $handledRequestClasses = $container->getParameter(self::CONTROLLER_MAPPING_PARAMETER);

        $multipartRequestClasses = [];
        foreach ($this->getEndpoints($container) as $endpoint) {
            if (EndpointInterface::FORMAT_MULTIPART !== $endpoint->getRequestFormat()) {
                continue;
            }

            $requestClass = $endpoint->getRequestClass();
            if (!in_array($requestClass, $handledRequestClasses, true)) {
                continue;
            }

            $multipartRequestClasses[$requestClass] = $requestClass;
        }

        if ([] === $multipartRequestClasses) {
            return;
        }

        throw new ConfigurationException(
            sprintf(
                'A PSR-17 "%s" must be wired to handle multipart/form-data endpoints: %s. '
                . 'Install a PSR-7 implementation (e.g. guzzlehttp/psr7, nyholm/psr7) '
                . 'and register its stream factory.',
                StreamFactoryInterface::class,
                implode(', ', $multipartRequestClasses)
            )
        );
    }

    /**
     * @return EndpointInterface[]
     */
    private function getEndpoints(ContainerBuilder $container): array
    {
        $endpoints = [];
        foreach ($container->findTaggedServiceIds(self::ENDPOINT_PROVIDER_TAG) as $id => $tags) {
            $provider = $container->resolveServices($container->getDefinition($id));

            // Misconfigured providers are reported by the components-bundle compiler pass.
            if (!$provider instanceof EndpointProviderInterface) {
                continue;
            }

            foreach ($provider->getEndpoints() as $endpoint) {
                if ($endpoint instanceof EndpointInterface) {
                    $endpoints[] = $endpoint;
                }
            }
        }

        return $endpoints;
    }
}
