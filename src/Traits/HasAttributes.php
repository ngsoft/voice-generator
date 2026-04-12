<?php

declare(strict_types=1);

namespace Traits;

/**
 * @phan-file-suppress PhanTypeMismatchReturn
 */
trait HasAttributes
{
    /** @var array<string,mixed> */
    private array $attributes = [];

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string|\Stringable $name): bool
    {
        return isset($this->attributes[(string) $name]);
    }

    public function removeAttribute(string|\Stringable $name): static
    {
        unset($this->attributes[(string) $name]);
        return $this;
    }

    public function clearAttributes(): static
    {
        return $this->setAttributes([]);
    }

    public function addAttribute(string|\Stringable $name, mixed $value): static
    {
        if ( ! $this->hasAttribute($name))
        {
            $this->setAttribute($name, $value);
        }

        return $this;
    }

    /**
     * @param \Generator&iterable<string|\Stringable,mixed> $attributes
     */
    public function addAttributes(iterable $attributes): static
    {
        foreach ($attributes as $key => $value)
        {
            if (is_int($key))
            {
                continue;
            }
            $this->addAttribute((string) $key, $value);
        }

        return $this;
    }

    public function setAttribute(string|\Stringable $name, mixed $value): static
    {
        if (null === $value)
        {
            return $this->removeAttribute($name);
        }
        $this->attributes[(string) $name] = $value;
        return $this;
    }

    /**
     * @param iterable<string|\Stringable,mixed> $attributes
     */
    public function setAttributes(iterable $attributes, bool $clear = true): static
    {
        if ($clear)
        {
            $this->attributes = [];
        }

        foreach ($attributes as $key => $value)
        {
            if (is_int($key))
            {
                continue;
            }

            if ($key instanceof \Stringable || is_string($key))
            {
                $this->setAttribute((string) $key, $value);
            }
        }

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

    /**
     * @param string     $name
     * @param callable   $callback
     * @param null|mixed $default
     *
     * @return static
     */
    public function updateAttribute(string $name, $callback, mixed $default = null): static
    {
        $this->setAttribute($name, value($callback, $this->getAttribute($name, $default)));
        return $this;
    }

    public function pullAttribute(string $name, mixed $default = null): mixed
    {
        try
        {
            return $this->getAttribute($name, $default);
        } finally
        {
            $this->removeAttribute($name);
        }
    }
}
