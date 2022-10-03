<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests\Store;

use Brzuchal\Scheduler\ScheduleState;
use Brzuchal\Scheduler\Store\ScheduleEntryNotFound;
use Brzuchal\Scheduler\Tests\Fixtures\FooMessage;
use Brzuchal\SchedulerBundle\Store\DoctrineScheduleStore;
use Brzuchal\SchedulerBundle\Tests\TestKernelTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

use function file_exists;
use function getcwd;
use function getenv;
use function hash;
use function microtime;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function substr;
use function touch;
use function unlink;

class DoctrineScheduleStoreTest extends TestKernelTestCase
{
    public const IDENTIFIER = 'acb2e22a-0ab9-4083-9693-460de808ebe4';
    protected Connection $connection;
    protected DoctrineScheduleStore $store;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('DATABASE_URL');
        if (empty($url) || ! str_starts_with($url, 'sqlite:///')) {
            return;
        }

        $databasePath = str_replace('sqlite:///', getcwd(), $url);
        file_exists($databasePath) && unlink($databasePath);
        touch($databasePath);
    }

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'url' => getenv('DATABASE_URL'),
            'driver' => 'pdo_sqlite',
        ]);
        $this->store = new DoctrineScheduleStore($this->connection);
        $this->store->setup();
    }

    public function testSetup(): void
    {
        $suffix = substr(hash('sha1', (string) microtime(true)), 0, 8);
        $dataTableName = 'scheduler_messages_' . $suffix;
        $store = new DoctrineScheduleStore($this->connection, $dataTableName);
        $schemaManager = $this->connection->createSchemaManager();
        $store->setup();
        $this->assertTrue($schemaManager->tablesExist([$dataTableName]));
        $schemaManager->dropTable($dataTableName);
    }

    public function testNotFound(): void
    {
        $this->connection->executeQuery('DELETE FROM `scheduler_messages`');
        $this->expectException(ScheduleEntryNotFound::class);
        $this->store->findSchedule(self::IDENTIFIER);
    }

    /** @depends testNotFound */
    public function testInsert(): void
    {
        $triggerDateTime = new DateTimeImmutable('tomorrow');
        $this->store->insertSchedule(
            self::IDENTIFIER,
            $triggerDateTime,
            new FooMessage(),
        );
        $entry = $this->fetchEntry();
        $this->assertNotEmpty($entry);
        $this->assertNull($entry['rule']);
        $this->assertNull($entry['start_at']);
    }

    /** @depends testInsert */
    public function testFind(): void
    {
        $schedule = $this->store->findSchedule(self::IDENTIFIER);
        $this->assertInstanceOf(FooMessage::class, $schedule->message());
        $this->assertGreaterThan(new DateTimeImmutable('now'), $schedule->triggerDateTime());
        $this->assertNull($schedule->rule());
        $this->assertNull($schedule->startDateTime());
    }

    /** @depends testInsert */
    public function testNoPending(): void
    {
        $identifiers = $this->store->findPendingSchedules(new DateTimeImmutable('now'));
        $this->assertEmpty($identifiers);
    }

    /** @depends testNoPending */
    public function testUpdate(): void
    {
        $previousEntry = $this->fetchEntry();
        $this->assertNotEmpty($previousEntry);
        $previous = $previousEntry['trigger_at'];
        $this->store->updateSchedule(
            self::IDENTIFIER,
            ScheduleState::Completed,
            new DateTimeImmutable('yesterday'),
        );
        $entry = $this->fetchEntry();
        // phpcs:disable
        $this->assertNotEmpty($entry['trigger_at']);
        $this->assertNotEquals($previous, $entry['trigger_at']);
        $this->assertEquals(ScheduleState::Completed->value, $entry['state']);
    }

    /** @depends testUpdate */
    public function testPending(): void
    {
        $this->connection->executeQuery('DELETE FROM `scheduler_messages` WHERE id = ?', [self::IDENTIFIER]);
        $this->store->insertSchedule(
            self::IDENTIFIER,
            new DateTimeImmutable('yesterday'),
            new FooMessage(),
        );
        $identifiers = $this->store->findPendingSchedules(new DateTimeImmutable('now'));
        $this->assertNotEmpty($identifiers);
        $this->assertContainsEquals(self::IDENTIFIER, $identifiers);
    }

    /** @depends testPending */
    public function testDelete(): void
    {
        $this->store->deleteSchedule(self::IDENTIFIER);
        $this->assertFalse($this->fetchEntry());
    }

    protected function fetchEntry(): mixed
    {
        return $this->connection->fetchAssociative(
            sprintf('SELECT * FROM `scheduler_messages` WHERE `id` = ?'),
            [self::IDENTIFIER],
        );
    }
}
