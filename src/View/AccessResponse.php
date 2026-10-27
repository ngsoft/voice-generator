<?php

namespace View;

class AccessResponse extends OpenApiResponseView
{
    public ?int $id                = null;

    public ?int $user_id           = null;

    public int $permission         = 0;

    public string $permission_type = '';

    public string $access_group    = '';
}
