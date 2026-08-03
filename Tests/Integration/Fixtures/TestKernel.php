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

namespace Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures;

use Auto1\ServiceAPIComponentsBundle\Auto1ServiceAPIComponentsBundle;
use Auto1\ServiceAPIHandlerBundle\Auto1ServiceAPIHandlerBundle;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App\DocumentEndpointProvider;
use Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App\StreamFactoryStub;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private bool $withStreamFactory;
    private string $varDir;

    public function __construct(bool $withStreamFactory, string $varDir)
    {
        $this->withStreamFactory = $withStreamFactory;
        $this->varDir = $varDir;

        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new Auto1ServiceAPIComponentsBundle(),
            new Auto1ServiceAPIHandlerBundle(),
        ];
    }

    /*
     * EndpointRouterCompilerPass scans <project_dir>/src for controllers, so the fixture
     * directory acts as the consumer application root.
     */
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->varDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->varDir . '/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'serializer' => ['enabled' => true],
            'property_access' => ['enabled' => true],
            'property_info' => ['enabled' => true],
        ]);

        $services = $container->services();

        $services
            ->set(DocumentEndpointProvider::class)
            ->tag('auto1.api.endpoint_provider', ['priority' => 0])
        ;

        if ($this->withStreamFactory) {
            $services->set(StreamFactoryInterface::class, StreamFactoryStub::class);
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
