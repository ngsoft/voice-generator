<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */
abstract class VariableAccessor
{
    protected static array $storage = [];

    final public static function getItem(string $name, mixed $defaultValue = null): mixed
    {
        if ( ! static::hasItem($name))
        {
            $value = value($defaultValue);

            if (is_callable($defaultValue))
            {
                static::setItem($name, $value);
            }
            return $value;
        }

        return static::decodeValue(static::$storage[static::class][$name]);
    }

    final public static function updateItem(string $name, callable $fn): mixed
    {
        static::setItem(
            $name,
            $value = $fn(
                static::getItem($name)
            )
        );

        return $value;
    }

    final public static function pullItem(string $name, mixed $defaultValue = null): mixed
    {
        try
        {
            return static::getItem($name, $defaultValue);
        } finally
        {
            static::removeItem($name);
        }
    }

    final public static function addItem(string $name, mixed $value): void
    {
        if (static::hasItem($name))
        {
            return;
        }

        static::setItem($name, $value);
    }

    final public static function setItem(string $name, mixed $value): void
    {
        static::removeItem($name);
        $value = value($value);

        if (isset($value))
        {
            static::$storage[static::class][$name] = static::encodeValue($value);
        }
    }

    final public static function hasItem(string $name): bool
    {
        static::initialize();
        return isset(static::$storage[static::class][$name]);
    }

    final public static function removeItem(string $name): void
    {
        static::initialize();
        unset(static::$storage[static::class][$name]);
    }

    protected static function decodeValue(mixed $value): mixed
    {
        if (is_string($value))
        {
            try
            {
                return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException)
            {
            }
            return $value;
        }

        if (is_array($value))
        {
            foreach ($value as &$_value)
            {
                $_value = self::decodeValue($_value);
            }
        }
        return $value;
    }

    protected static function encodeValue(mixed $value): string
    {
        if ( ! is_string($value))
        {
            if ($value instanceof Stringable && ! ($value instanceof JsonSerializable))
            {
                return strval($value);
            }

            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return $value;
    }

    protected static function initialize(): void
    {
        throw new LogicException(static::class . '::' . __FUNCTION__ . '() not implemented yet.');
    }
}
