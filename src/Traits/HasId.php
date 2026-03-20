<?php

namespace Traits;

use Sql\ActiveRecord;

/**
 * @extends ActiveRecord
 */
trait HasId
{
    protected static $primaryKey = 'id';

    protected ?int $id           = null;

    /**
     * @return null|int
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
