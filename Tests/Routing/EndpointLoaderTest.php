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

namespace Tests\Auto1\ServiceAPIHandlerBundle\Routing;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Auto1\ServiceAPIHandlerBundle\Routing\EndpointLoader;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class EndpointLoaderTest extends TestCase
{
    private const CONTROLLER = 'App\Controller\SomeController::someAction';

    public function testLoadStripsQueryStringFromRoutePath(): void
    {
        $routes = $this->loadRoutes('/v1/items?excludeIds={excludeIds}', 'GET');

        $route = $routes->get(RequestStub::class);
        $this->assertNotNull($route);
        $this->assertSame('/v1/items', $route->getPath());
        $this->assertSame(['GET'], $route->getMethods());
    }

    public function testLoadKeepsPathPlaceholders(): void
    {
        $routes = $this->loadRoutes('/v1/cars/{id}', 'POST');

        $route = $routes->get(RequestStub::class);
        $this->assertNotNull($route);
        $this->assertSame('/v1/cars/{id}', $route->getPath());
        $this->assertSame(['POST'], $route->getMethods());
    }

    public function testRouteWithQueryStringTemplateMatchesRequestWithoutQueryParams(): void
    {
        $routes = $this->loadRoutes('/v1/items?excludeIds={excludeIds}', 'GET');

        $matcher = new UrlMatcher($routes, new RequestContext('', 'GET'));
        $parameters = $matcher->match('/v1/items');

        $this->assertSame(self::CONTROLLER, $parameters['_controller']);
        $this->assertSame(RequestStub::class, $parameters['_route']);
    }

    private function loadRoutes(string $path, string $method): RouteCollection
    {
        $endpointProphecy = $this->prophesize(EndpointInterface::class);
        $endpointProphecy->getPath()->willReturn($path);
        $endpointProphecy->getMethod()->willReturn($method);

        $endpointRegistryProphecy = $this->prophesize(EndpointRegistryInterface::class);
        $endpointRegistryProphecy->getEndpoint(Argument::type(RequestStub::class))
            ->willReturn($endpointProphecy->reveal());

        $endpointLoader = new EndpointLoader(
            $endpointRegistryProphecy->reveal(),
            [self::CONTROLLER => RequestStub::class]
        );

        $routes = $endpointLoader->load('.', 'endpoint_handler');
        $this->assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }
}
