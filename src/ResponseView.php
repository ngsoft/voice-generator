<?php

use Symfony\Component\HttpFoundation\Response;

class ResponseView implements Stringable
{
    /** @var int */
    protected $statusCode = 200;

    /** @var string */
    protected $content    = '';

    /** @var HeaderManager */
    private $headers;

    /**
     * @var ?Response
     */
    private $response     = null;

    public function __construct()
    {
        $this->headers = new HeaderManager();
        $this->setContentType('text/html; charset=utf-8');
    }

    /**
     * @phan-suppress PhanParamSignatureRealMismatchReturnTypeInternal
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * @param Response $response
     *
     * @return ResponseView
     */
    public static function of(Response $response)
    {
        $instance           = new ResponseView();
        $instance->response = $response;

        $instance->setHeaders($response->headers->all());
        $instance->setStatusCode($response->getStatusCode());
        $instance->setContent($response->getContent() ?: '');
        return $instance;
    }

    /**
     * @return static
     */
    public static function newResponse()
    {
        return new static();
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     *
     * @return static
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     *
     * @return static
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * @param string $contentType
     *
     * @return static
     */
    public function setContentType($contentType)
    {
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->getHeaderLine('Content-Type');
    }

    /**
     * @param string $header
     *
     * @return static
     */
    public function removeHeader($header)
    {
        $this->headers->removeHeader($header);
        return $this;
    }

    /**
     * @param array $headers
     *
     * @return static
     */
    public function setHeaders(array $headers)
    {
        $this->headers->setHeaders($headers);
        return $this;
    }

    /**
     * @param string                                  $header
     * @param float|float[]|int|int[]|string|string[] ...$values
     *
     * @return static
     */
    public function setHeader($header, $values)
    {
        if (is_array($values))
        {
            $values = array_map(function ($val)
            {
                return (string) $val;
            }, $values);
        } else
        {
            $values = (string) $values;
        }
        $this->headers->setHeader($header, $values);
        return $this;
    }

    /**
     * @param string                                  $header
     * @param float|float[]|int|int[]|string|string[] ...$values
     *
     * @return static
     */
    public function addHeader($header, $values)
    {
        $this->headers->addHeader($header, $values);
        return $this;
    }

    /**
     * @param string $header
     *
     * @return string
     */
    public function getHeaderLine($header)
    {
        return $this->headers->getHeaderLine($header);
    }

    /**
     * @param string $header
     *
     * @return string[]
     */
    public function getHeader($header)
    {
        return $this->headers->getHeader($header);
    }

    /**
     * @return string
     */
    public function getRawHeaders()
    {
        return $this->headers->getRawHeaders();
    }

    /**
     * @return array<string, string>
     */
    public function getAllHeaders()
    {
        return $this->headers->toArray();
    }

    /**
     * @return Response
     */
    public function toResponse()
    {
        return $this->response ?? new Response($this->getContent(), $this->getStatusCode(), $this->getAllHeaders());
    }
}
