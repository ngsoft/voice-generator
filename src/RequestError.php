<?php

class RequestError extends RuntimeException
{
    private ?Action $action = null;

    public function __construct(
        private readonly Request $request,
        int $code = 0,
        string $message = ''
    ) {
        parent::__construct($message, $code);
        $this->request->setAttribute(__CLASS__, $this);
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(Action $action): static
    {
        $this->action = $action;
        return $this;
    }
}
