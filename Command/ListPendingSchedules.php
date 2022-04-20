<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\Store\ScheduleStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('scheduler:pending:list', 'Show pending scheduled messages list')]
final class ListPendingSchedules extends Command
{
    public function __construct(
        protected ScheduleStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'type',
            InputArgument::OPTIONAL,
            'Message type',
        );
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Limit listed',
            50,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = null;
        $limit = \intval($input->getOption('limit'));
        if ($input->hasOption('type')) {
            $type = $input->getOption('type');
        }

        $table = new Table($output);
        $table->setHeaders(['Id', 'Class', 'Release on', 'RRule', 'DTStart']);
        foreach ($this->store->findPendingSchedules(limit: $limit) as $identifier) {
            $schedule = $this->store->findSchedule($identifier);
            $class = \get_class($schedule->message());
            if ($type !== null && \is_a($class, $type, true) === false) {
                continue;
            }

            $table->addRow([
                $identifier,
                $class,
                $schedule->triggerDateTime()->format('Y-m-d H:i:s'),
                $schedule->rule()?->toString(),
                $schedule->startDateTime()?->format('Y-m-d H:i:s'),
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
