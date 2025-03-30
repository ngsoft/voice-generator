<?php

namespace Renderer;

class JsonResponse extends BaseResponse implements \JsonSerializable
{
    private mixed $data     = null;
    private bool $success   = true;
    private ?string $error  = null;
    private string $message = '';

    public function __construct()
    {
        $this->addHeader('Access-Control-Allow-Origin', '*');
        $this->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->setContentType('application/json; charset=utf-8');
    }

    public static function newNotFound(): static
    {
        return (new static())->setResponseCode(404)->setError('not found');
    }

    public static function newBadRequest(): static
    {
        return (new static())->setResponseCode(400)->setError('bad request');
    }

    public static function newBadMethod(): static
    {
        return (new static())->setResponseCode(405)->setError('bad method');
    }

    public static function newForbidden(): static
    {
        return (new static())->setResponseCode(403)->setError('forbidden');
    }

    public static function newInternalError(): static
    {
        return (new static())->setResponseCode(500)->setError('internal error');
    }

    public static function newUnauthorized(): static
    {
        return (new static())->setResponseCode(401)->setError('unauthorized');
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): static
    {
        $this->success = isset($data);
        $this->data    = $data;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): static
    {
        $this->success = $success;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(string $message, mixed ...$replacements): static
    {
        if ( ! empty($replacements))
        {
            $message = vsprintf($message, $replacements);
        }
        $this->error = $message;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getContent(): string
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function jsonSerialize(): array
    {
        $success = $this->success;
        $message = $this->message;

        if (isset($this->error))
        {
            $success = false;
            $message = $this->error;
        }

        $data    = $this->data;
        $resp    = ['success' => $success, 'message' => $message];

        if (isset($data))
        {
            $resp['data'] = $data;
        }

        return $resp;
    }
}
