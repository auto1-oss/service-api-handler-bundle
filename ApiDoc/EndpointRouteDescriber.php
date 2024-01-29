<?php

namespace Auto1\ServiceAPIHandlerBundle\ApiDoc;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use EXSyst\Component\Swagger\Operation;
use EXSyst\Component\Swagger\Parameter;
use EXSyst\Component\Swagger\Response;
use EXSyst\Component\Swagger\Schema;
use EXSyst\Component\Swagger\Swagger;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Routing\Route;

/**
 * Class EndpointRouteDescriber.
 *
 * @package Auto1\ServiceAPIHandlerBundle\ApiDoc
 * @deprecated For nelmio/api-doc-bundle v4 OpenApi used instead of Swagger.
 */
class EndpointRouteDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use RouteDescriberTrait;

    use ModelRegistryAwareTrait;

    /**
     * @var EndpointRegistryInterface
     */
    private $endpointRegistry;

    /**
     * @var string[]
     */
    private $controllerToRequestMapping;

    /**
     * @var PropertyInfoExtractorInterface
     */
    private $propertyExtractor;

    /**
     * EndpointRouteDescriber constructor.
     *
     * @param EndpointRegistryInterface $endpointRegistry
     * @param array $controllerToRequestMapping
     * @param PropertyInfoExtractorInterface $propertyExtractor
     */
    public function __construct(
        EndpointRegistryInterface $endpointRegistry,
        array $controllerToRequestMapping,
        PropertyInfoExtractorInterface $propertyExtractor
    ) {
        $this->endpointRegistry = $endpointRegistry;
        $this->controllerToRequestMapping = $controllerToRequestMapping;
        $this->propertyExtractor = $propertyExtractor;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(Swagger $api, Route $route, \ReflectionMethod $reflectionMethod)
    {
        $endpoint = $this->getEndpoint($route);

        if (!$endpoint instanceof EndpointInterface) {
            return;
        }

        $operation = $api
            ->getPaths()
            ->get($this->normalizePath($route->getPath()))
            ->getOperation(strtolower($endpoint->getMethod()));

        if (!$operation instanceof Operation) {
            return;
        }

        $this->fillEndpointTags($operation, $reflectionMethod->getDeclaringClass());
        $this->fillEndpointResponse($operation, $endpoint);
        $this->fillEndpointParameters($api, $operation, $route, $endpoint);
    }

    /**
     * @param Route $route
     * @return EndpointInterface|null
     */
    private function getEndpoint(Route $route)
    {
        $controller = $route->getDefault('_controller');

        if (!\array_key_exists($controller, $this->controllerToRequestMapping)) {
            return null;
        }

        $request  = $this->controllerToRequestMapping[$controller];
        $endpoint = $this->endpointRegistry->getEndpoint(new $request);

        return $endpoint;
    }

    /**
     * Fills the endpoint response documentation.
     *
     * @param Operation $operation
     * @param EndpointInterface $endpoint
     */
    private function fillEndpointResponse(Operation $operation, EndpointInterface $endpoint)
    {
        if (!class_exists($endpoint->getResponseClass())) {
            return;
        }

        $model  = new Model(new Type(Type::BUILTIN_TYPE_OBJECT, false, $endpoint->getResponseClass()));
        $ref    = $this->modelRegistry->register($model);
        $response = new Response();
        $response->merge(['schema' => (object) ['$ref' => $ref]]);
        $operation->getResponses()->set(200, $response);
    }

    /**
     * Fills the endpoint tags.
     * @param Operation $operation
     * @param \ReflectionClass $reflectionClass
     */
    private function fillEndpointTags(Operation $operation, \ReflectionClass $reflectionClass)
    {
        $className = $reflectionClass->getShortName();
        $classTag  = strtolower(preg_replace('/([a-zA-Z0-9])(?=[A-Z])/', '$1-', $className));

        $operation->merge(['tags' => [$classTag]]);
    }

    /**
     * Fills the endpoint parameters.
     *
     * @param Swagger $api
     * @param Operation $operation
     * @param Route $route
     * @param EndpointInterface $endpoint
     */
    private function fillEndpointParameters(
        Swagger $api,
        Operation $operation,
        Route $route,
        EndpointInterface $endpoint
    ) {
        if (!class_exists($endpoint->getRequestClass())) {
            return;
        }

        $routeParameters = $route->compile()->getPathVariables();
        $dtoParameters = $this->propertyExtractor->getProperties($endpoint->getRequestClass());

        //When all parameters are available in the route parameters
        if (null === $dtoParameters || count(array_diff($dtoParameters, $routeParameters)) == 0) {
            return;
        }

        $model = new Model(new Type(Type::BUILTIN_TYPE_OBJECT, false, $endpoint->getRequestClass()));
        $ref   = $this->modelRegistry->register($model);

        $this->modelRegistry->registerDefinitions();

        $properties = $api
            ->getDefinitions()
            ->get(array_slice(explode('/', $ref), -1, 1)[0])
            ->getProperties();

        //It removes the parameters which are available in the route from the request
        foreach ($routeParameters as $routeParameter) {
            $properties->remove($routeParameter);
        }

        $parameter = new Parameter([
            'in'       => 'body',
            'name'     => 'request',
            'schema'   => (object) ['$ref' => $ref],
            'required' => true,
        ]);

        $operation->getParameters()->add($parameter);
    }
}
