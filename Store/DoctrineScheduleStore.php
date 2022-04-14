<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Store;

use Brzuchal\Scheduler\ScheduleState;
use Brzuchal\Scheduler\Store\ScheduleEntryNotFound;
use Brzuchal\Scheduler\Store\ScheduleStore;
use Brzuchal\Scheduler\Store\ScheduleStoreEntry;
use Brzuchal\Scheduler\Store\SetupableScheduleStore;
use Brzuchal\Scheduler\Store\SimpleScheduleStoreEntry;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Types;

use function assert;
use function is_array;
use function serialize;
use function sprintf;
use function strlen;
use function unserialize;

final class DoctrineScheduleStore implements ScheduleStore, SetupableScheduleStore
{
    public const DEFAULT_EXECUTIONS_TABLE_NAME = 'schedule_exec';
    public const DEFAULT_DATA_TABLE_NAME = 'schedule_data';

    public function __construct(
        protected Connection $connection,
        protected string $executionTableName = self::DEFAULT_EXECUTIONS_TABLE_NAME,
        protected string $dataTableName = self::DEFAULT_DATA_TABLE_NAME,
    ) {
    }

    public function updateSchedule(string $identifier, DateTimeImmutable $triggerDateTime, ScheduleState $state): void
    {
        $this->connection->update($this->dataTableName, [
            'trigger_at' => $triggerDateTime,
            'state' => $state->value,
        ], ['id' => $identifier], [
            Types::DATETIME_IMMUTABLE,
            Types::STRING,
        ]);
    }

    /**
     * @throws Exception
     * @throws ScheduleEntryNotFound
     */
    public function findSchedule(string $identifier): ScheduleStoreEntry
    {
        $sql = sprintf(
            'SELECT `trigger_at`, `serialized`, `interval` FROM %s WHERE `id` = ?',
            $this->dataTableName,
        );
        $entry = $this->connection->prepare($sql)
            ->executeQuery([$identifier])
            ->fetchAssociative();

        if ($entry === false) {
            throw new ScheduleEntryNotFound('not found'); // TODO: make static factory method
        }

        assert(is_array($entry));
        $platform = $this->connection->getDatabasePlatform();

        return new SimpleScheduleStoreEntry(
            (new DateTimeTzImmutableType())->convertToPHPValue((string) $entry['trigger_at'], $platform),
            unserialize($entry['serialized']),
            (new DateIntervalType())->convertToPHPValue($entry['interval'] ?? null, $platform),
        );
    }

    public function insertSchedule(
        string $identifier,
        DateTimeImmutable $triggerDateTime,
        object $message,
        DateInterval|null $interval = null,
    ): void {
        $this->connection->insert($this->dataTableName, [
            'id' => $identifier,
            'trigger_at' => $triggerDateTime->setTimezone(new DateTimeZone('UTC')),
            'serialized' => serialize($message),
            'interval' => $interval,
            'state' => ScheduleState::Pending->value,
        ], [
            'trigger_at' => Types::DATETIME_IMMUTABLE,
            'interval' => Types::DATEINTERVAL,
            'state' => Types::STRING,
        ]);
    }

    /**
     * @psalm-return iterable<non-empty-string>
     *
     * @throws Exception
     *
     * @psalm-suppress InvalidReturnType
     */
    public function findPendingSchedules(DateTimeImmutable $date): iterable
    {
        $sql = sprintf(
            'SELECT `id` FROM %s WHERE `trigger_at` < ? AND `state` = ?',
            $this->dataTableName,
        );
        $params = [
            $date,
            ScheduleState::Pending->value,
        ];
        $types = [
            Types::DATETIME_IMMUTABLE,
            Types::STRING,
        ];

        /** @psalm-suppress InvalidReturnStatement */
        return $this->connection->executeQuery($sql, $params, $types)
            ->fetchFirstColumn();
    }

    /**
     * @throws Exception
     */
    public function deleteSchedule(string $identifier): void
    {
        $this->connection->delete($this->dataTableName, ['id' => $identifier]);
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    public function setup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schema = new Schema([], [], $schemaManager->createSchemaConfig());
        $this->addDataTableSchema($schema);
        $this->addExecutionsTableSchema($schema);
        $schemaDiff = $schemaManager->createComparator()->compareSchemas($schemaManager->createSchema(), $schema);
        foreach ($schemaDiff->toSaveSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    /**
     * @throws SchemaException
     */
    protected function addDataTableSchema(Schema $schema): void
    {
        $length = 1;
        foreach (ScheduleState::cases() as $scheduleState) {
            if (strlen($scheduleState->value) < $length) {
                continue;
            }

            $length = strlen($scheduleState->value);
        }

        $table = $schema->createTable($this->dataTableName);
        $table->addColumn('id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('trigger_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('serialized', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('interval', Types::DATEINTERVAL)
            ->setNotnull(false);
        $table->addColumn('state', Types::STRING, ['length' => $length]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['trigger_at', 'state']);
        $table->addIndex(['state']);
    }

    /**
     * @throws SchemaException
     */
    protected function addExecutionsTableSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->executionTableName);
        $table->addColumn('schedule_id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('iteration', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('executed_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->setPrimaryKey(['schedule_id', 'iteration']);
        $table->addForeignKeyConstraint(
            $this->dataTableName,
            ['schedule_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
        );
    }
}
