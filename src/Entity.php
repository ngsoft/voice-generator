<?php

/** @noinspection PhpUnhandledExceptionInspection */
/* @noinspection PhpDocMissingThrowsInspection */
/* @noinspection SqlType */

/* @noinspection PhpMissingReturnTypeInspection */

use NGSOFT\DataStructure\Set;
use NGSOFT\Facades\Container;
use Sql\Maker;
use Sql\QueryHelper;

abstract class Entity implements Maker
{
    protected int $id = 0;

    /**
     * @param array<string,mixed>|string $cond
     * @param string|string[]            $sort
     *
     * @phan-suppress PhanTypeMismatchReturn
     *
     * @return static[]
     */
    final public static function find(array|string $cond = [], array|string $sort = '', bool $asc = true, ?QueryHelper $conn = null): array
    {
        $conn ??= static::getConnection();

        $result = $conn->select('*')
            ->from(static::getTable());

        if ($cond)
        {
            $result->where($cond);
        }

        if ($sort)
        {
            $result->orderBy($sort, $asc);
        }

        $result = $result->execute();

        if ($result)
        {
            return $result->makeMany(static::class);
        }

        return [];
    }

    /**
     * @param array<string,mixed>|string $cond
     * @param string|string[]            $sort
     */
    final public static function findOne(array|string $cond = [], array|string $sort = '', bool $asc = true, ?QueryHelper $conn = null): ?static
    {
        $conn ??= static::getConnection();
        $result = $conn->select('*')
            ->from(static::getTable())->limit(1);

        if ($cond)
        {
            $result->where($cond);
        }

        if ($sort)
        {
            $result->orderBy($sort, $asc);
        }

        return $result->execute()?->make(static::class);
    }

    final public static function findById(int $id, ?QueryHelper $conn = null): ?static
    {
        $conn ??= static::getConnection();
        return $conn
            ->select('*')
            ->from(static::getTable())->limit(1)
            ->where(['id = ?' => $id])
            ->execute()
            ?->make(static::class);
    }

    abstract public static function migrate(QueryHelper $conn): void;

    public static function getConnection(): QueryHelper
    {
        return Container::get(QueryHelper::class);
    }

    public static function getTable(): string
    {
        return preg_replace_callback(
            '#[A-Z]#',
            fn ($x) => '_' . lcfirst($x[0]),
            lcfirst(
                basename(
                    str_replace(
                        '\\',
                        '/',
                        static::class
                    )
                )
            )
        );
    }

    final public function getId(): int
    {
        return $this->id;
    }

    public function save(?QueryHelper $conn = null): static
    {
        static $locks = new Set();

        if ($locks->has($this))
        {
            return $this;
        }

        try
        {
            $locks->add($this);

            if ($this->id)
            {
                $this::updateEntry($this, $conn);
                return $this;
            }
            $this::insertEntry($this, $conn);
            return $this;
        } finally
        {
            $locks->delete($this);
        }
    }

    final public function insertEntry(self $entity, ?QueryHelper $conn = null): bool
    {
        if ($entity->getId())
        {
            return false;
        }
        $conn ??= self::getConnection();

        try
        {
            $conn->dispatchEvent('insert:before', $entity);
            $data = get_object_vars($entity);
            unset($data['id']);

            if ($conn->insert($entity::getTable())
                ->values($data)
                ->execute()
                && $id = $conn->lastInsertId())
            {
                $entity->id = $id;
            }
        } finally
        {
            $conn->dispatchEvent('insert:after', $entity);
        }

        return (bool) $entity->getId();
    }

    final public static function updateEntry(self $entity, ?QueryHelper $conn = null): bool
    {
        if ( ! $entity->getId())
        {
            return false;
        }

        $conn ??= self::getConnection();

        try
        {
            $conn->dispatchEvent('update:before', $entity);
            $id   = $entity->getId();
            $data = get_object_vars($entity);
            unset($data['id']);

            return (bool) $conn->update($entity::getTable())->set($data)
                ->where(['id = ?' => $id])->execute();
        } finally
        {
            $conn->dispatchEvent('update:after', $entity);
        }
    }

    final public static function removeEntry(int|self $entity, ?QueryHelper $conn = null): bool
    {
        if ( ! is_int($entity))
        {
            $entity = $entity->getId();
        }

        if ( ! $entity)
        {
            return false;
        }

        $conn ??= self::getConnection();

        $conn->dispatchEvent('delete', $entity);

        return (bool) $conn
            ->delete(static::getTable())
            ->where('id = ?', $entity)
            ->execute();
    }

    /**
     * @param ?static $instance
     *
     * @return static
     */
    final public static function make(array $data, $instance = null)
    {
        $instance ??= new static();

        $keys = array_keys(get_object_vars($instance));

        foreach ($data as $field => $value)
        {
            if (in_array($field, $keys))
            {
                $instance->{$field} = $value;
            }
        }

        return $instance;
    }

    protected static function migrateManyToMany(QueryHelper $conn, string $other): void
    {
        if ($other)
        {
            $table = static::getTable();
            $conn->exec("CREATE TABLE IF NOT EXISTS `{$other}_{$table}` (
                `{$other}_id` int,
                `{$table}_id` int,
                KEY `{$other}_id` (`{$other}_id`),
                KEY `{$table}_id` (`{$table}_id`),
                PRIMARY KEY (`{$other}_id`, `{$table}_id`),
                FOREIGN KEY (`{$other}_id`) 
                    REFERENCES `{$other}`(`id`) 
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT,
                FOREIGN KEY (`{$table}_id`) 
                    REFERENCES `{$table}`(`id`) 
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            )");
        }
    }
}
