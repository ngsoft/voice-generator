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
}
