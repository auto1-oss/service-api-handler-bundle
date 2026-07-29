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

use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
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
    private const TARGET_PROVIDER_TAG = 'auto1.api.endpoint_provider';
    private const TARGET_PROVIDER_SERVICE_ID = 'target.endpoint_provider';
    private const TARGET_CONTROLLER_ACTION = 'App\Controller\UploadController::uploadAction';

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
    }

    private function getCut(): MultipartStreamFactoryCompilerPass
    {
        return new MultipartStreamFactoryCompilerPass();
    }

    private function registerEndpointProvider(string $requestClass, string $requestFormat): void
    {
        $definition = new Definition(EndpointProviderStub::class, [$requestClass, $requestFormat]);
        $definition->addTag(self::TARGET_PROVIDER_TAG);
        $this->container->setDefinition(self::TARGET_PROVIDER_SERVICE_ID, $definition);
    }

    public function testProcessThrowsWhenHandledMultipartEndpointAndFactoryMissing(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpointProvider(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $cut = $this->getCut();

        $this->expectException(ConfigurationException::class);
        $cut->process($this->container);
    }

    public function testProcessSucceedsWhenFactoryRegistered(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpointProvider(RequestStub::class, self::TARGET_MULTIPART_FORMAT);
        $this->container->register(StreamFactoryInterface::class);

        $cut = $this->getCut();

        $this->expectNotToPerformAssertions();
        $cut->process($this->container);
    }

    public function testProcessIgnoresClientOnlyMultipartEndpoints(): void
    {
        $targetMapping = [];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpointProvider(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $cut = $this->getCut();

        $this->expectNotToPerformAssertions();
        $cut->process($this->container);
    }

    public function testProcessIgnoresNonMultipartEndpoints(): void
    {
        $targetMapping = [self::TARGET_CONTROLLER_ACTION => RequestStub::class];

        $this->container->setParameter(self::TARGET_MAPPING_PARAMETER, $targetMapping);
        $this->registerEndpointProvider(RequestStub::class, self::TARGET_JSON_FORMAT);

        $cut = $this->getCut();

        $this->expectNotToPerformAssertions();
        $cut->process($this->container);
    }

    public function testProcessSucceedsWhenMappingParameterMissing(): void
    {
        $this->registerEndpointProvider(RequestStub::class, self::TARGET_MULTIPART_FORMAT);

        $cut = $this->getCut();

        $this->expectNotToPerformAssertions();
        $cut->process($this->container);
    }
}
