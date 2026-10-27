<?php

namespace View;

class UserResponse extends OpenApiResponseView
{
    public int $id             = 0;

    public string $login       = '';

    public ?string $full_name  = null;

    /**
     * @var string[]
     */
    public array $roles        = [];

    public bool $enabled       = true;

    //    public array $data = [];

    /**
     * @var ?AccessResponse[]
     */
    public ?array $permissions = null;
}
