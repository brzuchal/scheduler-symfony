<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests;

use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\SchedulerBundle\Store\DoctrineScheduleStore;

final class SchedulerBundleTest extends TestKernelTestCase
{
    public function testStoreDefinition(): void
    {
        $container = self::getContainer();
        $this->assertTrue($container->has('scheduler.store'));
        $this->assertInstanceOf(DoctrineScheduleStore::class, $container->get('scheduler.store'));
    }

    public function testExecutorDefinition(): void
    {
        $container = self::getContainer();
        $this->assertTrue($container->has('scheduler.executor'));
        $this->assertInstanceOf(ScheduleExecutor::class, $container->get('scheduler.executor'));
    }
}
