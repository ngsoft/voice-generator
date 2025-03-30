<?php

namespace Traits;

/**
 * @phan-file-suppress PhanTypeMismatchReturn
 */
trait HasAttributes
{
    private array $attributes = [];

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function removeAttribute(string $name): static
    {
        unset($this->attributes[$name]);
        return $this;
    }

    public function addAttribute(string $name, mixed $value): static
    {
        if ( ! $this->hasAttribute($name))
        {
            $this->setAttribute($name, $value);
        }

        return $this;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        if ( ! $this->hasAttribute($name))
        {
            return value($default);
        }
        return $this->attributes[$name];
    }
}
