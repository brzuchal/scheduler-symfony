<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\DependencyInjection;

use Brzuchal\SchedulerBundle\Store\DoctrineScheduleStore;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('scheduler');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->arrayNode('store')
                ->children()
                    ->scalarNode('driver')->defaultValue('doctrine')->end()
                    ->scalarNode('connection')->defaultValue('default')->end()
                    ->scalarNode('data_table')
                        ->defaultValue(
                            DoctrineScheduleStore::DEFAULT_EXECUTIONS_TABLE_NAME,
                        )
                    ->end()
                    ->scalarNode('exec_table')
                        ->defaultValue(
                            DoctrineScheduleStore::DEFAULT_DATA_TABLE_NAME,
                        )
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
