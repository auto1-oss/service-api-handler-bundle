<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\ServiceAPIHandlerBundle;

use Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass\EndpointRouterCompilerPass;
use Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass\MultipartStreamFactoryCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Auto1ServiceAPIHandlerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EndpointRouterCompilerPass());

        // After the components-bundle EndpointProviderCompilerPass (default priority 0), so
        // the endpoints are already baked into the registry definition when the guard runs.
        $container->addCompilerPass(
            new MultipartStreamFactoryCompilerPass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -10
        );
    }
}
