<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\Scheduler\Store\ScheduleStore;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('scheduler:release', 'Release scheduled message immediately')]
final class ReleaseSchedule extends Command
{
    use DescribesSchedule;

    protected function __construct(
        protected ScheduleExecutor $executor,
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
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Do you want to release this messages immediately? (yes/no)',
            true
        );
        if (!$helper->ask($input, $output, $question)) {
            $this->executor->execute($identifier);
            return Command::SUCCESS;
        }

        return self::FAILURE;
    }
}