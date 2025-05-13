<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Auto1\ServiceAPIHandlerBundle\DependencyInjection;

use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class Auto1ServiceAPIHandlerExtension
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

        if (!class_exists('EXSyst\Component\Swagger\Swagger') || class_exists('OpenApi\Annotations\OpenApi')) {
            $container->removeDefinition('auto1.route_describers.route_metadata');
        }
        if (!class_exists('OpenApi\Annotations\OpenApi')) {
            $container->removeDefinition('auto1.route_describers.open_api_route_describer');
        }
    }
}
