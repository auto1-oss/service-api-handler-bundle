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

namespace Auto1\ServiceAPIHandlerBundle\Routing;

use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class EndpointLoader extends Loader
{
    /**
     * @var EndpointRegistryInterface
     */
    private $endpointRegistry;

    /**
     * @var string[]
     */
    private $controllerToRequestMapping;

    /**
     * EndpointLoader constructor.
     * @param EndpointRegistryInterface $endpointRegistry
     * @param string[] $controllerToRequestMapping
     */
    public function __construct(EndpointRegistryInterface $endpointRegistry, array $controllerToRequestMapping)
    {
        $this->endpointRegistry = $endpointRegistry;
        $this->controllerToRequestMapping = $controllerToRequestMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function load(mixed $resource, ?string $type = null): object
    {
        $routes = new RouteCollection();

        foreach ($this->controllerToRequestMapping as $controller => $requestClass) {
            try {
                $endpoint = $this->endpointRegistry->getEndpoint(
                    (new \ReflectionClass($requestClass))->newInstanceWithoutConstructor()
                );
            } catch (ConfigurationException $e) {
                // Endpoint was not registered
                continue;
            }

            //TODO: go through $requestClass and set requirements \d+ etc based on type
            $route = new Route(
                $this->stripQueryString($endpoint->getPath()),
                ['_controller' => $controller]
            );
            $route->setMethods([$endpoint->getMethod()]);

            $routes->add($requestClass, $route);
        }

        return $routes;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'endpoint_handler' === $type;
    }

    /**
     * Endpoint paths may contain a query string template (e.g. "/v1/cars?ids={ids}") used by API clients
     * to build request URIs. Routes are matched against the path info only, so the query string must not
     * be part of the route path.
     */
    private function stripQueryString(string $path): string
    {
        return explode('?', $path, 2)[0];
    }
}
