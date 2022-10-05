<?php declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\Store\ScheduleStoreEntry;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

trait DescribesSchedule
{

    protected function describeSchedule(
        OutputInterface $output,
        string $identifier,
        ScheduleStoreEntry $schedule,
    ): void {
        $table = new Table($output);
        $rows = [
            ['Id', $identifier],
            ['Class', $schedule->message()::class],
            ['Trigger time', $schedule->triggerDateTime()->format('Y-m-d H:i:s')],
            ['Rule', $schedule->rule()?->toString()],
            ['Rule start time', $schedule->startDateTime()?->format('Y-m-d H:i:s')],
        ];
        $table->addRows($rows);
        $table->render();
    }
}
