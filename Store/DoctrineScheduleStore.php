<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Store;

use Brzuchal\RecurrenceRule\Rule;
use Brzuchal\RecurrenceRule\RuleFactory;
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

    public function updateSchedule(
        string $identifier,
        DateTimeImmutable $triggerDateTime,
        ScheduleState $state,
        Rule|null $rule = null,
        DateTimeImmutable|null $startDateTime = null
    ): void {
        $this->connection->update($this->dataTableName, [
            'trigger_at' => $triggerDateTime,
            'rule' => $rule?->toString(),
            'start_at' => $startDateTime,
            'state' => $state->value,
        ], ['id' => $identifier], [
            'trigger_at' => Types::DATETIME_IMMUTABLE,
            'rule' => Types::STRING,
            'start_at' => Types::DATETIME_IMMUTABLE,
            'state' => Types::STRING,
        ]);
    }

    /**
     * @throws Exception
     * @throws ScheduleEntryNotFound
     */
    public function findSchedule(string $identifier): ScheduleStoreEntry
    {
        $sql = sprintf(
            'SELECT `trigger_at`, `serialized`, `rule`, `start_at` FROM %s WHERE `id` = ?',
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
        $type = new DateTimeTzImmutableType();

        return new SimpleScheduleStoreEntry(
            $type->convertToPHPValue((string) $entry['trigger_at'], $platform),
            unserialize($entry['serialized']),
            !empty($entry['interval']) ? RuleFactory::fromString($entry['interval']) : null,
            !empty($entry['start_at']) ? $type->convertToPHPValue($entry['start_at'], $platform) : null,
        );
    }

    public function insertSchedule(
        string $identifier,
        DateTimeImmutable $triggerDateTime,
        object $message,
        Rule|null $rule = null,
        DateTimeImmutable|null $startDateTime = null,
    ): void {
        $utc = new DateTimeZone('UTC');
        $this->connection->insert($this->dataTableName, [
            'id' => $identifier,
            'trigger_at' => $triggerDateTime->setTimezone($utc),
            'serialized' => serialize($message),
            'rule' => $rule?->toString(),
            'start_at' => $startDateTime?->setTimezone($utc),
            'state' => ScheduleState::Pending->value,
        ], [
            'trigger_at' => Types::DATETIME_IMMUTABLE,
            'rule' => Types::STRING,
            'start_at' => Types::DATETIME_IMMUTABLE,
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
        $table->addColumn('rule', Types::TEXT)
            ->setNotnull(false);
        $table->addColumn('start_at', Types::DATETIME_IMMUTABLE)
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
