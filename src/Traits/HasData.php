<?php

namespace Traits;

use Sql\ActiveRecord;

/**
 * @extends ActiveRecord
 */
trait HasData
{
    protected array $data = [];

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public function addData(array $data)
    {
        foreach ($data as $name => $value)
        {
            if (is_string($name))
            {
                $this->addMeta($name, $value);
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function addMeta(string $name, mixed $value)
    {
        if ( ! $this->hasMeta($name))
        {
            $this->setMeta($name, $value);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function setMeta(string $name, mixed $value)
    {
        $this->data = array_replace($this->data, [$name => $value]);

        if (null === $value)
        {
            unset($this->data[$name]);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getMeta(string $name, mixed $default = null)
    {
        return $this->getData()[$name] ?? value($default, $name);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMeta(string $name): bool
    {
        return $this !== $this->getMeta($name, $this);
    }

    protected function initialize_metadata()
    {
        if ( ! isset(static::$types['data']))
        {
            static::$types['data'] = 'json';
            static::$hidden[]      = 'data';
        }
    }

    protected function initialize()
    {
        $this->initialize_metadata();
    }
}
