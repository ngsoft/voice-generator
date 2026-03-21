<?php

namespace Model;

use Sql\ValidationError;

abstract class DataModel implements \IteratorAggregate
{
    /** @var string[] */
    protected array $error             = [];

    /** @var string[] */
    protected array $definedProperties = [];

    public function __construct(?array $data = null)
    {
        try
        {
            null !== $data && $this->validateData($data);
        } catch (ValidationError $error)
        {
            $this->addError($error->getMessage());
        }
    }

    final public function __get(string $name)
    {
        return property_exists($this, $name)
            ? $this->{$name}
            : null;
    }

    final public function __isset(string $name): bool
    {
        return property_exists($this, $name) && isset($this->{$name});
    }

    final public function __set(string $name, $value): void
    {
        // noop
    }

    final public function __unset(string $name): void
    {
        // noop
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return static
     */
    public static function make(array $data): static
    {
        $instance = new static();
        $instance->validateData($data);
        return $instance;
    }

    public function getError(): string
    {
        return implode(',', $this->error);
    }

    public function isError(): bool
    {
        return ! empty($this->error);
    }

    /**
     * @return string[]
     */
    public function getDefinedProperties(): array
    {
        return $this->definedProperties;
    }

    public function getValidationErrors(): array
    {
        return $this->error;
    }

    /**
     * @return iterable<string,mixed>&\Traversable
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->definedProperties as $prop)
        {
            yield $prop => $this->{$prop};
        }
    }

    protected function addError(string $message, ?string $prop = null): static
    {
        return $this->addErrorMessage(
            $message,
            $prop
        );
    }

    protected function parseDate(?string $value, bool $start = false): ?\DateTimeInterface
    {
        if ( ! $value)
        {
            return null;
        }

        // JS date
        $value = str_replace('T', ' ', $value);

        /* Y-m */
        if (preg_match('#^\d{4}-(0[1-9]|1[0-2])$#', $value))
        {
            $value = date_create_immutable("{$value}-01")
                ->format($start ? 'Y-m-01' : 'Y-m-t');
        } /* Y */ elseif (preg_match('#^\d{4}$#', $value))
        {
            $value = date_create_immutable($start ? "{$value}-01-01" : "{$value}-12-31")
                ->format($start ? 'Y-m-01' : 'Y-m-t');
        }

        try
        {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable)
        {
            $stamp = strtotime($value);

            if (false === $stamp)
            {
                return null;
            }
            $date  = date_create_immutable(date('Y-m-d', $stamp));
        }

        if ( ! (int) $date->format('His'))
        {
            $date = $date->setTime(23, 59, 59);

            if ($start)
            {
                $date = $date->setTime(0, 0, 0);
            }
        }
        return $date;
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        $value = is_string($value) ? strtolower(trim($value)) : $value;

        return ! in_array($value, [0, '0', 'false', 'no', 'off', 'non', null, ''], true);
    }

    protected function getPropertyName(string $value): string
    {
        $prop = preg_replace_callback('#_([a-z])#i', fn ($x) => strtoupper($x[1]), $value);

        if (property_exists($this, $prop))
        {
            return $prop;
        }
        return $value;
    }

    protected function getRequired(array $data, string $property): mixed
    {
        $key = $this->getPropertyName($property);

        if ( ! isset($data[$key]))
        {
            throw ValidationError::make('%s is required', $property);
        }
        return $data[$property];
    }

    protected function checkRequired(array $data, string ...$properties): bool
    {
        $ok = ! empty($properties);

        foreach ($properties as $property)
        {
            try
            {
                $this->getRequired($data, $property);
            } catch (ValidationError $error)
            {
                $this->addError($error->getMessage(), $property);
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * @param \BackedEnum|class-string<\BackedEnum> $enum
     *
     * @return array
     */
    protected function getEnumValues(\BackedEnum|string $enum): array
    {
        return array_map(fn (\BackedEnum $case) => $case->value, $enum::cases());
    }

    protected function validateData(array $data)
    {
        foreach ($data as $key => $value)
        {
            if ( ! is_string($key) || ! isset($value))
            {
                continue;
            }

            $prop = $this->getPropertyName($key);

            if (property_exists($this, $prop))
            {
                try
                {
                    $this->{$prop}             = $value;
                    $this->definedProperties[] = $prop;
                } catch (\Throwable $error)
                {
                    throw ValidationError::make('%s invalid value', $prop);
                }
            }
        }
    }

    private function addErrorMessage(string $message, int|string|null $prop): static
    {
        if ( ! is_string($prop))
        {
            $this->error[] = $message;
            return $this;
        }

        $this->error[$prop] ??= $message;
        return $this;
    }
}
