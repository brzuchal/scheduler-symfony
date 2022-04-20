<?php

declare(strict_types=1);

use Brzuchal\SchedulerBundle\Command\ReleasePendingSchedules;
use Brzuchal\SchedulerBundle\Command\ListPendingSchedules;
use Brzuchal\SchedulerBundle\Command\ReleaseSchedule;
use Brzuchal\SchedulerBundle\Command\ShowSchedule;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()
        ->autoconfigure(true);
    $services->set(ReleasePendingSchedules::class)
        ->args([
            new Reference('scheduler.store'),
            new Reference('scheduler'),
        ]);
    $services->set(ListPendingSchedules::class)
        ->arg(0, new Reference('scheduler.store'));
    $services->set(ReleaseSchedule::class)
        ->args([
            new Reference('scheduler.store'),
            new Reference('scheduler'),
        ]);
    $services->set(ShowSchedule::class)
        ->arg(0, new Reference('scheduler.store'));
};
