<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Executor;

use Brzuchal\Scheduler\Executor\SimpleScheduleExecutor;
use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\Scheduler\Store\ScheduleStore;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerExecutorFactory
{
    public function __invoke(
        ScheduleStore $store,
        MessageBusInterface $messageBus,
    ): ScheduleExecutor {
        return new SimpleScheduleExecutor(
            $store,
            $messageBus->dispatch(...),
        );
    }
}
