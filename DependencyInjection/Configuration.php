<?php

namespace LCavero\DoctrinePaginatorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('lcavero_doctrine_paginator')->children();
        $this->addDoctrinePaginatorSection($rootNode);

        return $treeBuilder;
    }

    /**
     * addDoctrinePaginatorSection
     * @param NodeBuilder $builder
     */
    protected function addDoctrinePaginatorSection(NodeBuilder $builder)
    {
        $builder
            ->arrayNode('mapping')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('boolean_true_values')
                    ->prototype('scalar')->end()
                    ->defaultValue([1, "true"])->end()
                ->end()
                ->children()
                    ->arrayNode('boolean_false_values')
                    ->prototype('scalar')->end()
                    ->defaultValue([0, "false"])->end()
                ->end()
            ->end()
        ;
    }
}
