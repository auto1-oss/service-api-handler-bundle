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

namespace Tests\Auto1\ServiceAPIHandlerBundle\Integration;

use Auto1\ServiceAPIComponentsBundle\Exception\Core\ConfigurationException;
use Auto1\ServiceAPIHandlerBundle\ArgumentResolver\ServiceRequestResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App\UploadController;
use Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App\UploadDocumentRequest;
use Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\TestKernel;

class ContainerCompilationTest extends TestCase
{
    private const TARGET_MAPPING_PARAMETER = 'auto1.api_handler.controller_request_mapping';
    private const TARGET_RESOLVER_SERVICE_ID = 'auto1.api_handler.argument_resolver.service_request';
    private const TARGET_VAR_DIR_PREFIX = '/auto1-api-handler-integration-';

    private string $varDir;

    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $tmpDir = sys_get_temp_dir();
        $uniqueSuffix = uniqid();
        $this->varDir = $tmpDir . self::TARGET_VAR_DIR_PREFIX . $uniqueSuffix;
    }

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            $this->kernel->shutdown();
            $this->kernel = null;
        }

        $filesystem = new Filesystem();
        $filesystem->remove($this->varDir);
    }

    private function getCut(bool $withStreamFactory): TestKernel
    {
        $this->kernel = new TestKernel($withStreamFactory, $this->varDir);

        return $this->kernel;
    }

    public function testContainerCompilesAndMapsHandledEndpoint(): void
    {
        $targetWithStreamFactory = true;
        $targetControllerAction = UploadController::class . '::uploadAction';

        $target = $this->getCut($targetWithStreamFactory);

        $target->boot();

        $container = $target->getContainer();

        $mapping = $container->getParameter(self::TARGET_MAPPING_PARAMETER);
        self::assertArrayHasKey($targetControllerAction, $mapping);
        self::assertSame(UploadDocumentRequest::class, $mapping[$targetControllerAction]);

        $testContainer = $container->get('test.service_container');
        $resolver = $testContainer->get(self::TARGET_RESOLVER_SERVICE_ID);
        self::assertInstanceOf(ServiceRequestResolver::class, $resolver);
    }

    public function testContainerCompilationFailsWithoutStreamFactoryForMultipartEndpoint(): void
    {
        $targetWithStreamFactory = false;

        $target = $this->getCut($targetWithStreamFactory);

        $this->expectException(ConfigurationException::class);
        $target->boot();
    }
}
