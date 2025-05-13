<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\ServiceAPIHandlerBundle\ApiDoc;

use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointInterface;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointRegistryInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use OpenApi\Annotations\Get;
use OpenApi\Annotations\Items;
use OpenApi\Annotations\JsonContent;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\RequestBody;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use OpenApi\Generator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Routing\Route;

/**
 * Class EndpointRouteDescriber.
 *
 * @package Auto1\ServiceAPIHandlerBundle\ApiDoc
 */
class OpenApiEndpointRouteDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use RouteDescriberTrait;
    use ModelRegistryAwareTrait;

    private const MEDIA_TYPE = 'application/json';

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

    public function __construct(
        EndpointRegistryInterface $endpointRegistry,
        array $controllerToRequestMapping,
        PropertyInfoExtractorInterface $propertyExtractor
    ) {
        $this->endpointRegistry = $endpointRegistry;
        $this->controllerToRequestMapping = $controllerToRequestMapping;
        $this->propertyExtractor = $propertyExtractor;
    }

    public function describe(OpenApi $api, Route $route, \ReflectionMethod $reflectionMethod)
    {
        $endpoint = $this->getEndpoint($route);

        if (!$endpoint instanceof EndpointInterface) {
            return;
        }

        $operation = $this->getOperations($api, $route)[0] ?? null;
        if (!$operation instanceof Operation) {
            return;
        }

        $this->fillEndpointTags($operation, $reflectionMethod->getDeclaringClass());
        $this->fillEndpointResponse($operation, $endpoint);
        $this->fillEndpointParameters($operation, $route, $endpoint);

        $components = $api->components;

        $properties = [];
        if ($components !== Generator::UNDEFINED) {
            $properties = [
                'schemas' => $components->schemas ,
                'responses' => $components->responses,
                'parameters' => $components->parameters,
                'examples' => $components->examples,
                'requestBodies' => $components->requestBodies,
                'headers' => $components->headers,
                'securitySchemes' => $components->securitySchemes,
                'links' => $components->links,
                'callbacks' => $components->callbacks,
            ];
        }

        $api->components = new Components($properties);
    }

    private function getEndpoint(Route $route)
    {
        $controller = $route->getDefault('_controller');

        if (!\array_key_exists($controller, $this->controllerToRequestMapping)) {
            return null;
        }

        $request = $this->controllerToRequestMapping[$controller];
        $endpoint = $this->endpointRegistry->getEndpoint(new $request);

        return $endpoint;
    }

    private function fillEndpointResponse(Operation $operation, EndpointInterface $endpoint)
    {
        $isArrayResponse = false;
        $responseClass = $endpoint->getResponseClass();
        if (!$responseClass) {
            return;
        }

        if (strpos($responseClass, '[]') !== false) {
            $responseClass = str_replace('[]', '', $responseClass);
            $isArrayResponse = true;
        }
        if (!class_exists($responseClass)) {
            return;
        }

        $model = new Model(new Type(Type::BUILTIN_TYPE_OBJECT, false, $responseClass));
        $ref = $this->modelRegistry->register($model);

        if ($isArrayResponse) {
            $schema = new Items(['type' => 'array', 'items' => new Items(['ref' => $ref])]);
        } else {
            $schema = new Schema(['schema' => $ref, 'ref' => $ref]);
        }
        $content = [
            new MediaType([
                'mediaType' => self::MEDIA_TYPE,
                'schema' => $schema,
            ]),
        ];

        $response = new Response([
            'description' => 'OK',
            'response' => 200,
            'content' => $content,
        ]);
        $operation->responses = [$response];
    }

    private function fillEndpointTags(Operation $operation, \ReflectionClass $reflectionClass)
    {
        $className = $reflectionClass->getShortName();
        $classTag = strtolower(preg_replace('/([a-zA-Z0-9])(?=[A-Z])/', '$1-', $className));

        $operation->tags = [$classTag];
    }

    private function fillEndpointParameters(
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

        /**
         * To avoid this error but also provide request body fields this hack is required
         * <The PropertyInfo component was not able to guess the type of ArrayObject::$arrayCopy>
         */
        if (is_subclass_of($endpoint->getRequestClass(), \ArrayObject::class)) {
            $type = new Type(Type::BUILTIN_TYPE_OBJECT, false, \stdClass::class);
        } else {
            $type = new Type(Type::BUILTIN_TYPE_OBJECT, false, $endpoint->getRequestClass());
        }
        $ref = $this->modelRegistry->register(new Model($type));

        $operation->requestBody = new RequestBody([
            'request' => $endpoint->getRequestClass(),
            'content' => [
                new MediaType([
                    'mediaType' => self::MEDIA_TYPE,
                    'schema' => new Schema(['ref' => $ref, 'schema' => $ref]),
                ]),
            ],
        ]);
    }
}
