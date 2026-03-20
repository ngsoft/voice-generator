<?php

namespace Traits;

use Sql\ActiveRecord;

/**
 * @extends ActiveRecord
 */
trait HasCreatedAt
{
    protected ?\DateTimeInterface $created_at = null;

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at ??= date_create_immutable();
    }

    /**
     * @return static
     */
    public function setCreatedAt(\DateTimeInterface|string $created_at)
    {
        $this->created_at = $created_at instanceof \DateTimeInterface
            ? $created_at
            : date_create_immutable($created_at);
        return $this;
    }

    protected function initialize_lifecycle_created_at()
    {
        if ( ! isset(static::$types['created_at']))
        {
            static::$hidden[]            = 'created_at';
            static::$types['created_at'] = \DateTimeImmutable::class;
        }
        $this->created_at = date_create_immutable();
    }

    protected function initialize()
    {
        $this->initialize_lifecycle_created_at();
    }
}
