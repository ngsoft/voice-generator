<?php

class Config
{
    private static $config = [];

    /**
     * @param string $name
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public static function getItem(string $name, $defaultValue = null)
    {
        return var_get($name, self::$config, $defaultValue);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public static function addItem(string $name, $value)
    {
        if ( ! isset(self::$config[$name]))
        {
            static::setItem($name, $value);
        }
        return static::getItem($name);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public static function setItem(string $name, $value)
    {
        return self::$config[$name] = $value;
    }

    /**
     * @param iterable<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function setMany(iterable $values)
    {
        $set = [];

        foreach ($values as $name => $value)
        {
            if (is_string($name))
            {
                $set[$name] = static::setItem($name, $value);
            }
        }
        return $set;
    }

    /**
     * @param string   $name
     * @param callable $updater
     *
     * @return mixed
     */
    public static function updateItem(string $name, $updater)
    {
        return self::$config[$name] = $updater(self::getItem($name));
    }
}
