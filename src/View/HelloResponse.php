<?php

namespace View;

use OpenApi\Attributes as OA;
use Record\User;

#[OA\Schema]
class HelloResponse extends SuccessResponse
{
    #[OA\Property(description: 'Hello message', type: 'string', example: 'Hello {name}!', nullable: false)]
    public ?string $message       = '';

    #[OA\Property(description: 'api url', nullable: false)]
    protected string $url         = '';

    #[OA\Property(description: 'web url', nullable: false)]
    protected string $page_url    = '';

    #[OA\Property(description: 'User', nullable: true)]
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
