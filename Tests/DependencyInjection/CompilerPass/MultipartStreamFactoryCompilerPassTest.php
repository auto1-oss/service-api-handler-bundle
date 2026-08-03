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

use Auto1\ServiceAPIComponentsBundle\DependencyInjection\CompilerPass\EndpointProviderCompilerPass;
use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
use Auto1\ServiceAPIComponentsBundle\Service\Endpoint\EndpointImmutable;
use Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass\MultipartStreamFactoryCompilerPass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tests\Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestStub;

class MultipartStreamFactoryCompilerPassTest extends TestCase
{
    private const TARGET_MULTIPART_FORMAT = 'multipart';
    private const TARGET_JSON_FORMAT = 'json';
    private const TARGET_MAPPING_PARAMETER = 'auto1.api_handler.controller_request_mapping';
    private const TARGET_CONTROLLER_ACTION = 'App\Controller\UploadController::uploadAction';
    private const TARGET_HTTP_METHOD = 'POST';
    private const TARGET_PATH = '/v1/upload';

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
    }

    private function getCut(): MultipartStreamFactoryCompilerPass
    {
        return new MultipartStreamFactoryCompilerPass();
    }

    private function registerEndpoint(string $requestClass, string $requestFormat): void
    {
        $registryId = EndpointProviderCompilerPass::SERVICE_ENDPOINT_REGISTRY;
        if (!$this->container->hasDefinition($registryId)) {
            $newRegistryDefinition = new Definition();
            $this->container->setDefinition($registryId, $newRegistryDefinition);
        }

        $endpointDefinition = new Definition(EndpointImmutable::class);
        $endpointDefinition->setArguments([
            self::TARGET_HTTP_METHOD,
            null,
            self::TARGET_PATH,
            $requestFormat,
            $requestClass,
            self::TARGET_JSON_FORMAT,
            null,
            null,
        ]);

        $registryDefinition = $this->container->getDefinition($registryId);
        $registryDefinition->addMethodCall(
            EndpointProviderCompilerPass::METHOD_REGISTER_ENDPOINT,
            [$endpointDefinition]
        );
    }

    public function testProcessThrowsWhenHandledMultipartEndpointAndFactoryMissing(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpoint(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $target = $this->getCut();

        $this->expectException(ConfigurationException::class);
        $target->process($this->container);
    }

    public function testProcessSucceedsWhenFactoryRegistered(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpoint(RequestStub::class, self::TARGET_MULTIPART_FORMAT);
        $this->container->register(StreamFactoryInterface::class);

        $target = $this->getCut();

        $this->expectNotToPerformAssertions();
        $target->process($this->container);
    }

    public function testProcessIgnoresClientOnlyMultipartEndpoints(): void
    {
        $targetMapping = [];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpoint(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $target = $this->getCut();

        $this->expectNotToPerformAssertions();
        $target->process($this->container);
    }

    public function testProcessIgnoresNonMultipartEndpoints(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpoint(RequestStub::class, self::TARGET_JSON_FORMAT);

        $target = $this->getCut();

        $this->expectNotToPerformAssertions();
        $target->process($this->container);
    }

    public function testProcessSucceedsWhenMappingParameterMissing(): void
    {
        $this->registerEndpoint(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $target = $this->getCut();

        $this->expectNotToPerformAssertions();
        $target->process($this->container);
    }

    public function testProcessSucceedsWhenRegistryDefinitionMissing(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);

        $target = $this->getCut();

        $this->expectNotToPerformAssertions();
        $target->process($this->container);
    }
}
