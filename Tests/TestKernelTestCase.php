<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class TestKernelTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
