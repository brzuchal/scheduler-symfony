<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests\Fixtures;

use Brzuchal\Scheduler\Tests\Fixtures\FooMessage;

class FooMessageHandler
{
    public FooMessage $handled;

    public function __invoke(FooMessage $message): void
    {
        $this->handled = $message;
    }
}
