<?php

namespace View;

use Interfaces\HasOpenApiDataModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Traits\HasAttributes;

class OpenApiResponseView implements \JsonSerializable
{
    use HasAttributes;

    /** @var array<array<string,mixed>|string> */
    private static $__properties = [];

    final public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    final public function toResponse(int $status = 200): JsonResponse
    {
        return new JsonResponse(
            $this->jsonSerialize(),
            $status
        );
    }

    final public function toResponseView(int $status = 200): \ResponseView
    {
        return \ResponseView::of($this->toResponse($status));
    }

    /**
     * @param array<string,mixed>|\JsonSerializable<array<string,mixed>> $data
     *
     * @return static
     */
    public static function make(array|\JsonSerializable $data = [])
    {
        $class      = $data instanceof HasOpenApiDataModel ? $data::getOpenApiDataModel() : static::class;
        $data       = $data instanceof \JsonSerializable ? $data->jsonSerialize() : $data;

        if ( ! is_array($data))
        {
            throw new \InvalidArgumentException('Data must be an array or JsonSerializable');
        }

        $instance   = new $class();
        $properties = self::class === $class ? $data : self::reflect($class);

        foreach ($properties as $property => $default)
        {
            if ( ! is_string($property))
            {
                continue;
            }
            $value = self::transform($data[$property] ?? $default);
            isset($value) && $instance->addAttribute($property, $value);
        }
        return $instance;
    }

    private static function transform(mixed $value): mixed
    {
        if (is_array($value))
        {
            $value = array_map(function ($value)
            {
                return static::transform($value);
            }, $value);
        } elseif ($value instanceof HasOpenApiDataModel)
        {
            $value = self::make($value);
        } elseif ($value instanceof \JsonSerializable)
        {
            $value = $value->jsonSerialize();
        } elseif ($value instanceof \DateTimeInterface)
        {
            $value = $value->format('Y-m-d H:i:s');
        } elseif ($value instanceof \BackedEnum)
        {
            $value = $value->value;
        } elseif ($value instanceof \Stringable)
        {
            $value = (string) $value;
        }
        return $value;
    }

    private static function reflect(object|string $class): array
    {
        if (isset(self::$__properties[$class]))
        {
            return self::$__properties[$class];
        }

        try
        {
            $result                            = [];
            $reflector                         = new \ReflectionClass($class);

            foreach ($reflector->getProperties() as $property)
            {
                if ( ! $property->isPublic() || $property->isStatic())
                {
                    continue;
                }
                $result[$property->getName()] = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
            }
            return self::$__properties[$class] = $result;
        } catch (\ReflectionException)
        {
        }
        return [];
    }
}
