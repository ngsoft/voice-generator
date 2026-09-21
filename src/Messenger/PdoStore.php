<?php

declare(strict_types=1);

namespace Messenger;

use Symfony\Component\Messenger\Exception\TransportException;

/**
 * Persists Messenger envelopes in a SQL table using the project's \Sql helpers.
 */
final readonly class PdoStore
{
    public function __construct(
        private string $connectionName = \SqlConnector::DEFAULT_CONNECTION,
        private string $tableName = 'messenger_messages',
        private int $redeliverTimeout = 60,
    ) {}

    /**
     * Creates the messenger table (idempotent) by running the messenger migrations.
     */
    public function setup(): bool
    {
        return migrate_database(
            \SqlConnector::getConnection($this->connectionName),
            'messenger'
        );
    }

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    public function driverType(): string
    {
        return \SqlConnector::getConnection($this->connectionName)->type();
    }

    public function insert(string $queueName, string $body, string $headers, ?string $availableAt = null): string
    {
        $now = $this->now();

        try
        {
            $ok = \Sql::easyQuery(
                "INSERT INTO {$this->tableName} (body, headers, queue_name, created_at, available_at, delivered_at)"
                . ' VALUES (?, ?, ?, ?, ?, NULL)',
                [$body, $headers, $queueName, $now, $availableAt ?? $now],
                $this->connectionName
            );
        } catch (\Throwable $err)
        {
            // The project's \Sql layer throws (rather than returning falsy) whenever
            // throw-on-error is active (the default, see src/init.php), e.g. when the
            // messenger table is missing. Surface it the same way as a falsy result below.
            throw new TransportException(
                sprintf('Failed to enqueue message to %s (%s)', $this->tableName, $this->connectionName),
                0,
                $err
            );
        }

        if ( ! $ok)
        {
            throw new TransportException(
                sprintf('Failed to enqueue message to %s (%s)', $this->tableName, $this->connectionName)
            );
        }

        return (string) \Sql::getLastInsertId($this->connectionName);
    }

    /**
     * Whether the messenger table exists on this connection.
     *
     * The project's \Sql layer throws (rather than returning falsy) when throw-on-error is
     * active (the default, see src/init.php), so a missing table surfaces as an exception
     * here too — caught below and turned into a plain `false`.
     */
    public function isSetup(): bool
    {
        try
        {
            if ('mysql' === $this->driverType())
            {
                return ! empty(\Sql::describeTable($this->tableName, null, $this->connectionName));
            }

            // \Sql::describeTable() only implements the mysql (SHOW COLUMNS) branch; for
            // other drivers (e.g. sqlite) it always returns an empty array, so fall back to
            // a driver-aware existence probe instead.
            return null !== \Sql::easyQuery(
                "SELECT 1 FROM {$this->tableName} LIMIT 1",
                [],
                $this->connectionName
            );
        } catch (\Throwable $err)
        {
            return false;
        }
    }

    public function get(string $queueName): ?array
    {
        $this->redeliverStale($queueName);

        $now  = $this->now();
        $stmt = \Sql::easyQuery(
            "SELECT * FROM {$this->tableName}"
            . ' WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?'
            . ' ORDER BY available_at, id LIMIT 1',
            [$queueName, $now],
            $this->connectionName
        );

        $row  = $stmt?->fetchOne(\Sql\FETCH_ASSOC);

        if ( ! $row)
        {
            return null;
        }

        \Sql::easyQuery(
            "UPDATE {$this->tableName} SET delivered_at = ? WHERE id = ?",
            [$now, $row['id']],
            $this->connectionName
        );

        return $row;
    }

    public function deleteById(string $id): void
    {
        \Sql::easyQuery(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [$id],
            $this->connectionName
        );
    }

    public function count(string $queueName): int
    {
        $stmt = \Sql::easyQuery(
            "SELECT COUNT(*) AS c FROM {$this->tableName} WHERE queue_name = ? AND delivered_at IS NULL",
            [$queueName],
            $this->connectionName
        );
        $row  = $stmt?->fetchOne(\Sql\FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(string $queueName, ?int $limit = null): array
    {
        $sql  = "SELECT * FROM {$this->tableName} WHERE queue_name = ? AND delivered_at IS NULL"
            . ' ORDER BY available_at ASC, id ASC';

        if (null !== $limit)
        {
            $sql .= ' LIMIT ' . max(0, $limit);
        }

        $stmt = \Sql::easyQuery($sql, [$queueName], $this->connectionName);

        return $stmt ? $stmt->fetchAll(\Sql\FETCH_ASSOC) : [];
    }

    public function find(string $id): ?array
    {
        $stmt = \Sql::easyQuery(
            "SELECT * FROM {$this->tableName} WHERE id = ? LIMIT 1",
            [$id],
            $this->connectionName
        );

        return ($stmt?->fetchOne(\Sql\FETCH_ASSOC)) ?: null;
    }

    /**
     * Releases rows a dead worker left marked as delivered past the redeliver timeout.
     */
    private function redeliverStale(string $queueName): void
    {
        if ( ! $this->redeliverTimeout)
        {
            return;
        }
        $threshold = date('Y-m-d H:i:s', time() - $this->redeliverTimeout);

        \Sql::easyQuery(
            "UPDATE {$this->tableName} SET delivered_at = NULL"
            . ' WHERE queue_name = ? AND delivered_at IS NOT NULL AND delivered_at < ?',
            [$queueName, $threshold],
            $this->connectionName
        );
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
