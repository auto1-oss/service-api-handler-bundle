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

use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Auto1\ServiceAPIRequest\ServiceRequestInterface;

/**
 * Class EndpointRouterCompilerPass
 */
class EndpointRouterCompilerPass implements CompilerPassInterface
{
    public const CONTROLLER_SUFFIX = 'Controller';
    public const ACTION_SUFFIX = 'Action';
    public const EXCLUDES_IN_VENDOR = [
        'symfony/framework-bundle'
    ];

    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container): void
    {
        $cmp = new ComposerResource();

        $services = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if (strpos($id, '.abstract.instanceof.') === 0) {
                continue;
            }

            $class = $definition->getClass();
            if ($class === null) {
                continue;
            }
            $services[$id] = $class;
        }

        $classToServiceMapping = array_flip($this->filterControllers($services));

        $rootPath = $container->getParameter('kernel.project_dir') . '/src';
        $vendorPaths = $cmp->getVendors();

        $paths = array_merge([$rootPath], $vendorPaths);

        $finder = new Finder();
        $finder->name(sprintf('*%s.php', self::CONTROLLER_SUFFIX));
        $finder->in($paths);
        $finder->exclude(self::EXCLUDES_IN_VENDOR);

        foreach ($finder as $file) {
            try {
                require_once($file->getRealPath());
            } catch (\Throwable $e) {
                // `require_once()` failed, might be unsupported PHP version
            }
        }

        $controllers = $this->filterControllers(get_declared_classes());

        $appControllers = [];
        $vendorControllers = [];

        foreach ($controllers as $controller) {
            $reflectionClass = new \ReflectionClass($controller);
            if (strpos($reflectionClass->getFileName(), $rootPath) === 0) {
                $appControllers[] = $controller;
            } else {
                $vendorControllers[] = $controller;
            }
        }

        $mapping = array_merge(
            $this->buildMappingFromRouteDetails(
                $this->getRouteDetailsForControllers($vendorControllers),
                $classToServiceMapping
            ),
            $this->buildMappingFromRouteDetails(
                $this->getRouteDetailsForControllers($appControllers),
                $classToServiceMapping
            )
        );

        $container->setParameter('auto1.api_handler.controller_request_mapping', $mapping);
    }

    /**
     * @param array<int, array{request: string, controller: string, action: string}> $routeDetails
     * @param array<string, string> $classToServiceMapping
     *
     * @return array<string, string>
     */
    private function buildMappingFromRouteDetails($routeDetails, $classToServiceMapping)
    {
        $mapping = [];

        foreach ($routeDetails as $routeDetail) {
            $controllerKey = $classToServiceMapping[$routeDetail['controller']] ?? $routeDetail['controller'];

            /**
             * https://symfony.com/blog/new-in-symfony-4-1-deprecated-the-bundle-notation
             */
            $mapping[sprintf('%s::%s', $controllerKey, $routeDetail['action'])] = $routeDetail['request'];
        }

        return $mapping;
    }

    /**
     * @param string[] $controllers
     *
     * @return array<int, array{request: string, controller: string, action: string}>
     * @throws \ReflectionException
     */
    private function getRouteDetailsForControllers(array $controllers): array
    {
        $routeDetails = [];

        foreach ($controllers as $controller) {
            $reflectionClass = new \ReflectionClass($controller);

            $reflectionMethodCollection = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

            $actions = $this->filterActions(array_column($reflectionMethodCollection, 'name'));

            foreach ($actions as $action) {
                $reflectionMethod = $reflectionClass->getMethod($action);

                $resolvedRequests = [];

                foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                    $type = $reflectionParameter->getType();

                    if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                        continue;
                    }

                    $className = $type->getName();
                    if (!class_exists($className) || (new \ReflectionClass($className))->isAbstract()) {
                        continue;
                    }

                    if (is_subclass_of($className, ServiceRequestInterface::class)) {
                        $resolvedRequests[] = $className;
                    }
                }

                if (1 === count($resolvedRequests)) {
                    $routeDetails[] = [
                        'request' => current($resolvedRequests),
                        'controller' => $reflectionClass->getName(),
                        'action' => $action,
                    ];
                }
            }
        }

        return $routeDetails;
    }

    /**
     * @param array<int|string, string> $classes
     *
     * @return array<int|string, string>
     */
    private function filterControllers(array $classes): array
    {
        return \array_filter($classes, function ($v) {
            return \substr($v, -\strlen(self::CONTROLLER_SUFFIX)) === self::CONTROLLER_SUFFIX;
        });
    }

    /**
     * @param string[] $methods
     *
     * @return string[]
     */
    private function filterActions(array $methods): array
    {
        return \array_filter($methods, function ($v) {
            return \substr($v, -\strlen(self::ACTION_SUFFIX)) === self::ACTION_SUFFIX;
        });
    }
}
