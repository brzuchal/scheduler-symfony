<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests\Executor;

use Brzuchal\Scheduler\MessageScheduler;
use Brzuchal\Scheduler\ScheduleExecutor;
use Brzuchal\Scheduler\Store\SetupableScheduleStore;
use Brzuchal\Scheduler\Tests\Fixtures\FooMessage;
use Brzuchal\SchedulerBundle\Tests\Fixtures\FooMessageHandler;
use Brzuchal\SchedulerBundle\Tests\TestKernel;
use Brzuchal\SchedulerBundle\Tests\TestKernelTestCase;
use DateTimeImmutable;

use function assert;

final class MessengerExecutorFactoryTest extends TestKernelTestCase
{
    /**
     * @psalm-var class-string
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected static $class = TestKernel::class;

    protected function setUp(): void
    {
        $store = self::getContainer()->get('scheduler.store');
        assert($store instanceof SetupableScheduleStore);
        $store->setup();
    }

    public function testExecutor(): void
    {
        $executor = self::getContainer()->get('scheduler.executor');
        assert($executor instanceof ScheduleExecutor);
        $scheduler = self::getContainer()->get('scheduler');
        assert($scheduler instanceof MessageScheduler);
        $message = new FooMessage();
        $token = $scheduler->schedule(
            new DateTimeImmutable('+10 minutes'),
            $message,
        );
        $executor->execute($token->tokenId);
        $handler = self::getContainer()->get('test.foo_handler');
        assert($handler instanceof FooMessageHandler);
        $this->assertNotEmpty($handler->handled);
        $this->assertEquals($message, $handler->handled);
    }
}
