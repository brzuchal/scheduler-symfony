<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\DependencyInjection;

use Brzuchal\Scheduler\Store\InMemoryScheduleStore;
use Brzuchal\SchedulerBundle\Store\DoctrineScheduleStore;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function dirname;
use function dump;
use function sprintf;

final class SchedulerExtension extends Extension
{
    /**
     * @param mixed[] $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->load('console.php');
        $loader->load('services.php');
        $config = $this->processConfiguration(new Configuration(), $configs);
        if (! array_key_exists('store', $config)) {
            return;
        }

        $driver = 'in_memory';
        if (array_key_exists('driver', $config['store'])) {
            $driver = $config['store']['driver'];
        }

        switch ($driver) {
            case 'in_memory':
                $container->setAlias('scheduler.store', InMemoryScheduleStore::class);
                break;
            case 'doctrine':
                $container->setAlias('scheduler.store', DoctrineScheduleStore::class);
                $definition = $container->getDefinition(DoctrineScheduleStore::class);
                $doctrineConnection = sprintf(
                    'doctrine.dbal.%s_connection',
                    $config['store']['connection'] ?? 'default',
                );
                $definition->replaceArgument(0, new Reference($doctrineConnection));
                $dataTableName = $config['store']['data_table'] ?? DoctrineScheduleStore::DEFAULT_DATA_TABLE_NAME;
                $definition->replaceArgument(1, $dataTableName);
                // phpcs:ignore
                $executionsTableName = $config['store']['exec_table'] ?? DoctrineScheduleStore::DEFAULT_EXECUTIONS_TABLE_NAME;
                $definition->replaceArgument(2, $executionsTableName);
                break;
        }
    }
}
