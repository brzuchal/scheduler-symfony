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
     * @throws Exception|ScheduleEntryNotFound
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
            throw new ScheduleEntryNotFound(sprintf(
                'Schedule entry identified by %s not found',
                $identifier,
            ));
        }

        assert(is_array($entry));
        $platform = $this->connection->getDatabasePlatform();
        $type = new DateTimeTzImmutableType();

        return new SimpleScheduleStoreEntry(
            $type->convertToPHPValue((string) $entry['trigger_at'], $platform),
            unserialize($entry['serialized']),
            !empty($entry['rule']) ? RuleFactory::fromString($entry['rule']) : null,
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
            'created_at' => new DateTimeImmutable('now'),
        ], [
            'trigger_at' => Types::DATETIME_IMMUTABLE,
            'rule' => Types::STRING,
            'start_at' => Types::DATETIME_IMMUTABLE,
            'state' => Types::STRING,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * @psalm-return iterable<non-empty-string>
     *
     * @psalm-suppress InvalidReturnType
     */
    public function findPendingSchedules(
        DateTimeImmutable|null $beforeDateTime = null,
        int|null $limit = null
    ): iterable {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('id')
            ->from($this->dataTableName)
            ->where('state = ?')
            ->setParameter(0, ScheduleState::Pending->value, Types::STRING);
        if ($beforeDateTime !== null) {
            $queryBuilder->andWhere('trigger_at < ?')
                ->setParameter(1, $beforeDateTime, Types::DATETIME_IMMUTABLE);
        }

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }


        /** @psalm-suppress InvalidReturnStatement */
        return $queryBuilder->fetchFirstColumn();
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
        $table->addColumn('id', Types::STRING, ['length' => 36])
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
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['trigger_at', 'state']);
        $table->addIndex(['state']);
    }
}
