<?php

namespace Auto1\ServiceAPIHandlerBundle;

use Auto1\ServiceAPIHandlerBundle\DependencyInjection\CompilerPass\EndpointRouterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Auto1ServiceAPIHandlerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new EndpointRouterCompilerPass());
    }
}
