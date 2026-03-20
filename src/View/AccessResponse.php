<?php

namespace View;

use Enum\AccessGroup;
use Enum\Permission;
use OpenApi\Attributes as OA;

#[OA\Schema]
class AccessResponse extends OpenApiResponseView
{
    #[OA\Property(description: 'Permission identifier', type: 'integer', nullable: true, minimum: 1)]
    public ?int $id                = null;

    #[OA\Property(description: 'User identifier', type: 'integer', nullable: true, minimum: 1)]
    public ?int $user_id           = null;

    #[OA\Property(description: 'Permission type', enum: Permission::class)]
    public int $permission         = 0;

    #[OA\Property(description: 'Permission type', enum: ['none', 'read', 'write'])]
    public string $permission_type = '';

    #[OA\Property(description: 'Access Group name', enum: AccessGroup::class)]
    public string $access_group    = '';
}
