<?php

use Traits\HasAttributes;

class Request
{
    use HasAttributes;

    private array $headerNames = [];

    public function __construct(
        private string $uri,
        private string $method,
        private array $headers,
        private array $parameters,
    ) {
        foreach ($this->headers as $name => $value)
        {
            $this->headerNames[strtolower($name)] = $name;
        }
    }

    public function parseToken(): string
    {
        if (preg_match('/Bearer\s(\S+)/', $this->getHeader('authorization'), $matches))
        {
            return $matches[1];
        }
        return '';
    }

    public function hasParameter(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    public function addParameter(string $name, mixed $value): static
    {
        if ( ! $this->hasParameter($name))
        {
            $this->setParameter($name, $value);
        }
        return $this;
    }

    public function setParameter(string $name, mixed $value): static
    {
        $this->parameters[$name] = decode_value(value($value));
        return $this;
    }

    public function getParameter(string $name, mixed $defaultValue = ''): mixed
    {
        return $this->parameters[$name] ?? value($defaultValue);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRawHeaders(): string
    {
        return implode("\n", $this->headers);
    }

    public function getHeader(string $name): string
    {
        $lower = strtolower($name);

        if (isset($this->headerNames[$lower]))
        {
            return $this->headers[$this->headerNames[$lower]];
        }
        return '';
    }
}
