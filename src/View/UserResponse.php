<?php

namespace View;

use Enum\Role;
use OpenApi\Attributes as OA;

#[OA\Schema]
class UserResponse extends OpenApiResponseView
{
    #[OA\Property(description: 'User identifier', type: 'integer', nullable: false, minimum: 1)]
    public int $id             = 0;

    #[OA\Property(description: 'Username', nullable: false)]
    public string $login       = '';

    #[OA\Property(description: 'Full name', nullable: true)]
    public ?string $full_name  = null;

    /**
     * @var string[]
     */
    #[OA\Property(description: 'Roles', type: 'array', items: new OA\Items(enum: Role::class))]
    public array $roles        = [];

    #[OA\Property(description: 'User enabled', nullable: false)]
    public bool $enabled       = true;

    //    #[OA\Property(description: 'User data', type: 'object', nullable: false)]
    //    public array $data = [];

    /**
     * @var ?AccessResponse[]
     */
    #[OA\Property(description: 'User permissions')]
    public ?array $permissions = null;
}
