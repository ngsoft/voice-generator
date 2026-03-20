<?php

namespace Traits;

use Sql\ActiveRecord;
use Sql\Event\UpdateEntity;

/**
 * @extends ActiveRecord
 */
trait HasUpdatedAt
{
    use HasCreatedAt;

    protected ?\DateTimeInterface $updated_at = null;

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updated_at ??= date_create_immutable();
    }

    /**
     * @return static
     */
    public function setUpdatedAt(\DateTimeInterface|string $updated_at)
    {
        $this->updated_at = $updated_at instanceof \DateTimeInterface
            ? $updated_at
            : date_create_immutable($updated_at);
        return $this;
    }

    protected function initialize_lifecycle_updated_at()
    {
        $this->initialize_lifecycle_created_at();

        if ( ! isset(static::$types['updated_at']))
        {
            static::$hidden[]            = 'updated_at';
            static::$types['updated_at'] = \DateTimeImmutable::class;
        }

        $this->updated_at = date_create_immutable();
        $this->subscribe(
            UpdateEntity::class,
            fn () => $this->updated_at = date_create_immutable(),
            true
        );
    }

    protected function initialize()
    {
        $this->initialize_lifecycle_updated_at();
    }
}
