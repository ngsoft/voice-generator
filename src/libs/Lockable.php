<?php


interface Lockable
{
    /**
     * Lock the object.
     * @return void
     */
    public function lock();

    /**
     * Unlock the object.
     * @return void
     */
    public function unlock();


    /**
     * Get the lock status.
     * @return bool
     */
    public function isLocked();
}

