<?php

/** @noinspection PhpIllegalPsrClassPathInspection */
interface Lockable
{
    /**
     * Lock the object.
     */
    public function lock();

    /**
     * Unlock the object.
     */
    public function unlock();

    /**
     * Get the lock status.
     *
     * @return bool
     */
    public function isLocked();
}
