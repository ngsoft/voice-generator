<?php

namespace Worker;

/**
 * Lock a resource for multi tasking.
 */
class PidLock extends \Mutex
{
    private static array $locks = [];

    /**
     * Acquire a lock.
     *
     * @param string $name
     * @param float  $seconds
     *
     * @return bool
     */
    public static function lock(string $name, float $seconds = 300)
    {
        $mutex           = self::getLock($name);
        $mutex->duration = (float) max(1, $seconds);

        try
        {
            return $mutex->block($mutex->duration);
        } catch (\TimedOutMutexException)
        {
            return false;
        }
    }

    /**
     * Release a lock.
     *
     * @param string $name
     *
     * @return bool
     */
    public static function unlock(string $name)
    {
        return self::getLock($name)->release();
    }

    private static function getLock(string $name)
    {
        if ( ! isset(self::$locks[$name]))
        {
            $mutex              = new static($name);
            $mutex->file        = resolve_path('%data%/locks', "{$name}.tmp");
            self::$locks[$name] = $mutex;
        }
        return self::$locks[$name];
    }
}
