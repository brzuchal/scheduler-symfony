<?php

declare(strict_types=1);

use Brzuchal\SchedulerBundle\Command\ExecutePending;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->set(ExecutePending::class)
        ->args([
            new Reference('scheduler.store'),
            new Reference('scheduler'),
        ]);
};
