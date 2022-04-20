<?php

declare(strict_types=1);

use Brzuchal\Scheduler\MessageScheduler;
use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\Scheduler\Store\InMemoryScheduleStore;
use Brzuchal\SchedulerBundle\Executor\MessengerExecutorFactory;
use Brzuchal\SchedulerBundle\Store\DoctrineScheduleStore;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->set(InMemoryScheduleStore::class);
    $services->set(DoctrineScheduleStore::class)
        ->args([
            abstract_arg('Doctrine connection name'),
            abstract_arg('data table name'),
        ]);
    $services->alias('scheduler.store', DoctrineScheduleStore::class);
    $services->set(MessengerExecutorFactory::class);
    $services->set('scheduler.executor', ScheduleExecutor::class)
        ->factory(service(MessengerExecutorFactory::class))
        ->args([
            new Reference('scheduler.store'),
            new Reference('messenger.default_bus'),
        ]);
    $services->set(MessageScheduler::class)
        ->arg(0, new Reference('scheduler.store'));
    $services->alias('scheduler', MessageScheduler::class);
};
