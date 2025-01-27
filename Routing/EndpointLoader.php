<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public function load($resource, $type = null)
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
                $endpoint->getPath(),
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
    public function supports($resource, $type = null)
    {
        return 'endpoint_handler' === $type;
    }
}
