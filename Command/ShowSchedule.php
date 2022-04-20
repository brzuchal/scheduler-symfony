<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\Store\ScheduleStore;
use Brzuchal\Scheduler\Store\ScheduleStoreEntry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('scheduler:show', 'Show scheduled message')]
final class ShowSchedule extends Command
{
    use DescribesSchedule;

    protected function __construct(
        protected ScheduleStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'id',
            InputArgument::REQUIRED,
            'Schedule id',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = (string) $input->getOption('id');
        $schedule = $this->store->findSchedule($identifier);
        $this->describeSchedule($output, $identifier, $schedule);
        $output->writeln('<info>Message:</info>');
        $dump = new Dumper($output);
        $output->writeln($dump($schedule->message()));

        return self::SUCCESS;
    }
}
