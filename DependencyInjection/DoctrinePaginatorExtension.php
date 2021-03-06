<?php

namespace LCavero\DoctrinePaginatorBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class DoctrinePaginatorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->getDefinition('lcav_doctrine_paginator')->addArgument($config['mapping']['boolean_true_values']);
        $container->getDefinition('lcav_doctrine_paginator')->addArgument($config['mapping']['boolean_false_values']);
        $container->getDefinition('lcav_doctrine_paginator')->addArgument($config['search']['strict_mode']);
    }

    /**
     * getAlias
     * @return string
     */
    public function getAlias()
    {
        return "lcavero_doctrine_paginator";
    }
}
