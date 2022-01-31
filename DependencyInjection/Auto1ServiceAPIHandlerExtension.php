<?php

namespace Auto1\ServiceAPIHandlerBundle\DependencyInjection;

use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class PricingExtension
 */
class Auto1ServiceAPIHandlerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__) . '/Resources' . '/config')
        );

        //Load config files
        $loader->load('services.yml');

        if (!class_exists(NelmioApiDocBundle::class)) {
            $container->removeDefinition('auto1.route_describers.route_metadata');
        }
    }
}
