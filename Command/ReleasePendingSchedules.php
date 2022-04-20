<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\Scheduler\Store\ScheduleStore;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('scheduler:pending:release', 'Release pending scheduled messages')]
final class ReleasePendingSchedules extends Command
{
    public function __construct(
        protected ScheduleStore $store,
        protected ScheduleExecutor $executor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Execute pending against specific date-time',
            'now',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('date')) {
            $dateTimeString = $input->getOption('date');
        }

        if (empty($dateTimeString)) {
            $dateTimeString =  'now';
        }

        $dateTime = new DateTimeImmutable($dateTimeString);
        foreach ($this->store->findPendingSchedules($dateTime) as $identifier) {
            $output->writeln('<info>Executing <comment>{$identifier}</comment></info>');
            $this->executor->execute($identifier);
        }

        return self::SUCCESS;
    }
}
