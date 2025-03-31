<?php

/** @noinspection PhpMissingReturnTypeInspection */
class BaseModel
{
    final public static function make(array $data, ?self $instance = null): static
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
}
