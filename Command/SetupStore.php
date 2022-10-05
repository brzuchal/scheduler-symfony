<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Command;

use Brzuchal\Scheduler\Store\ScheduleStore;
use Brzuchal\Scheduler\Store\SetupableScheduleStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

#[AsCommand('scheduler:setup-store', 'Setup storage')]
final class SetupStore extends Command
{
    use DescribesSchedule;

    public function __construct(
        protected ScheduleStore $store,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!($this->store instanceof SetupableScheduleStore)) {
            throw new UnexpectedValueException('Configured store don\'t require setup');
        }

        $this->store->setup();

        return self::SUCCESS;
    }
}
