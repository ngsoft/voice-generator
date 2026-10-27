<?php

namespace View;

use Record\User;

class HelloResponse extends SuccessResponse
{
    public ?string $message       = '';

    protected string $url         = '';

    protected string $page_url    = '';

    protected ?UserResponse $user = null;

    public function setUrl(string $url): static
    {
        return $this->setAttribute('url', $url);
    }

    public function setPageUrl(string $page_url): static
    {
        return $this->setAttribute('page_url', $page_url);
    }

    public function setUser(User|UserResponse|null $user): static
    {
        return $this->setAttribute('user', $user instanceof User ? $user->toView() : $user);
    }
}
