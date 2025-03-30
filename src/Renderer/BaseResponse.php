<?php

namespace Renderer;

class BaseResponse implements \Response
{
    protected string $content  = '';

    /** @var array<string, string[]> */
    private array $headers     = [];

    /** @var array<string,string> */
    private array $headerNames = [];
    private int $responseCode  = 200;

    public function __toString(): string
    {
        return $this->getContent();
    }

    public function render(): never
    {
        $content = $this->getContent();
        $len     = strlen($content);
        $this->addHeader('Content-Length', $len);
        http_response_code($this->responseCode);

        foreach (explode("\n", $this->getRawHeaders()) as $header)
        {
            header($header);
        }
        @ob_end_clean();
        echo $content;

        if (true === \Env::getItem('APP_DEBUG'))
        {
            $log      = sprintf('RESPONSE code[%d]: %s', $this->responseCode, \CurlHandler::getReasonPhrase($this->responseCode));

            if (str_contains($this->getContentType(), '/json'))
            {
                $log .= sprintf(', JSON = %s', $content);
            }
            \ApplicationLogger::getLogger()->log($log);
            $duration = microtime(true) - constant_get('START_TIME');
            $value    = sprintf('%.03f sec', $duration);

            if ($duration < 1)
            {
                $value = sprintf('%d ms', $duration * 1000);
            }
            \ApplicationLogger::getLogger()->log('DURATION: %s', [$value]);
        }

        exit;
    }

    public static function newResponse(): static
    {
        return new static();
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function setResponseCode(int $responseCode): static
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getContentType(): string
    {
        return $this->getHeaderLine('Content-Type');
    }

    public function setContentType(string $contentType): static
    {
        return $this->setHeader('Content-Type', $contentType);
    }

    public function removeHeader(string $header): static
    {
        unset($this->headers[$this->getHeaderName($header)], $this->headerNames[strtolower($header)]);
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $this->headerNames = [];

        foreach ($headers as $name => $values)
        {
            if (is_array($values))
            {
                $this->addHeader($name, ...$values);
            } elseif (is_scalar($values) && ! is_bool($values))
            {
                $this->addHeader($name, $values);
            }
        }
        return $this;
    }

    public function setHeader(string $header, float|int|string ...$values): static
    {
        $this->removeHeader($header);
        return $this->addHeader($header, ...$values);
    }

    public function addHeader(string $header, float|int|string ...$values): static
    {
        $name                     = strtolower($header);
        $this->headerNames[$name] = $real = $this->getHeaderName($header);
        $this->headers[$real] ??= [];

        foreach ($values as $value)
        {
            if ('' === $value)
            {
                continue;
            }
            $this->headers[$real][] = (string) $value;
        }

        return $this;
    }

    public function getHeaderLine(string $header): string
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * @return string[]
     */
    public function getHeader(string $header): array
    {
        $header = strtolower($header);

        if ( ! isset($this->headerNames[$header]))
        {
            return [];
        }
        return $this->headers[$this->headerNames[$header]];
    }

    public function getRawHeaders(): string
    {
        $str = '';

        foreach (array_keys($this->headerNames) as $name)
        {
            $str .= sprintf("%s: %s\n", $this->headerNames[$name], $this->getHeaderLine($name));
        }
        return rtrim($str);
    }

    private function getHeaderName(string $header): string
    {
        return ucfirst(preg_replace_callback('#-[a-z]#', fn ($x) => strtoupper($x[0]), strtolower($header)));
    }
}
