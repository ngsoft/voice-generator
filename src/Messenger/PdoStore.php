<?php

declare(strict_types=1);

namespace Messenger;

/**
 * Persists Messenger envelopes in a SQL table using the project's \Sql helpers.
 */
final class PdoStore
{
    public function __construct(
        private readonly string $connectionName = '0',
        private readonly string $tableName = 'messenger_messages',
        private readonly int $redeliverTimeout = 3600,
    ) {}

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

    /**
     * Creates the messenger table (idempotent) by running the messenger migrations.
     */
    public function setup(): bool
    {
        return (bool) migrate_database(
            \SqlConnector::getConnection($this->connectionName),
            'messenger'
        );
    }

    public function insert(string $queueName, string $body, string $headers, ?string $availableAt = null): string
    {
        $now = $this->now();
        $ok  = \Sql::easyQuery(
            "INSERT INTO {$this->tableName} (body, headers, queue_name, created_at, available_at, delivered_at)"
            . ' VALUES (?, ?, ?, ?, ?, NULL)',
            [$body, $headers, $queueName, $now, $availableAt ?? $now],
            $this->connectionName
        );

        return $ok ? (string) \Sql::getLastInsertId($this->connectionName) : '0';
    }

    public function get(string $queueName): ?array
    {
        $this->redeliverStale($queueName);

        $now  = $this->now();
        $stmt = \Sql::easyQuery(
            "SELECT * FROM {$this->tableName}"
            . ' WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?'
            . ' ORDER BY available_at ASC, id ASC LIMIT 1',
            [$queueName, $now],
            $this->connectionName
        );

        $row = $stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null;

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
        $row  = $stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null;

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(string $queueName, ?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE queue_name = ? AND delivered_at IS NULL"
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

        return ($stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null) ?: null;
    }

    /**
     * Releases rows a dead worker left marked as delivered past the redeliver timeout.
     */
    private function redeliverStale(string $queueName): void
    {
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
