<?php

namespace Enum;

enum AccessGroup: string
{
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this)
        {
            self::Api => __('Api'),
            default   => $this->name
        };
    }

    public function getDescription(): string
    {
        return match ($this)
        {
            self::Api => __('Gives access to the API'),
            default   => $this->value
        };
    }
}
