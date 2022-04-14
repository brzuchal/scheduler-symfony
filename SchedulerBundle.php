<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle;

use Brzuchal\SchedulerBundle\DependencyInjection\SchedulerExtension;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SchedulerBundle extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new SchedulerExtension();
    }
}
