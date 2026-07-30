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

use Auto1\ServiceAPIComponentsBundle\DependencyInjection\CompilerPass\EndpointProviderCompilerPass;
use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointImmutable;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function implode;
use function in_array;
use function sprintf;

/**
 * Fails container compilation when a controller-handled multipart endpoint is registered
 * but no PSR-17 stream factory is available — so the misconfiguration surfaces on deploy
 * instead of on the first upload request.
 *
 * Covers endpoints wired through the generated endpoints.yaml and the `*Controller::*Action`
 * convention; manually-routed handlers are the integrator's responsibility and fail at
 * runtime with the LogicException thrown by MultipartRequestDataExtractor instead.
 *
 * Must run after the components-bundle EndpointProviderCompilerPass has baked the endpoints
 * into the registry definition — hence the negative pass priority in the bundle class.
 */
class MultipartStreamFactoryCompilerPass implements CompilerPassInterface
{
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
     * Reads the endpoints the components-bundle EndpointProviderCompilerPass has already baked
     * into the registry definition — instead of instantiating every tagged provider (and its
     * constructor dependency graph) a second time on each compile.
     *
     * @return EndpointInterface[]
     */
    private function getEndpoints(ContainerBuilder $container): array
    {
        if (!$container->hasDefinition(EndpointProviderCompilerPass::SERVICE_ENDPOINT_REGISTRY)) {
            return [];
        }

        $registryDefinition = $container->getDefinition(
            EndpointProviderCompilerPass::SERVICE_ENDPOINT_REGISTRY
        );

        $endpoints = [];
        foreach ($registryDefinition->getMethodCalls() as [$methodName, $arguments]) {
            if (EndpointProviderCompilerPass::METHOD_REGISTER_ENDPOINT !== $methodName) {
                continue;
            }

            $endpointDefinition = $arguments[0] ?? null;
            if (!$endpointDefinition instanceof Definition
                || EndpointImmutable::class !== $endpointDefinition->getClass()
            ) {
                continue;
            }

            // Scalar constructor args only — no services resolved, no provider constructors
            // run; an arg-order change in the vendor pass would fail loudly here.
            $endpoints[] = new EndpointImmutable(...$endpointDefinition->getArguments());
        }

        return $endpoints;
    }
}
