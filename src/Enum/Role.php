<?php

namespace Enum;

enum Role: string
{
    case USER        = 'ROLE_USER';
    case ADMIN       = 'ROLE_ADMIN';
    case SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public function getGroup(): array
    {
        return match ($this)
        {
            self::SUPER_ADMIN => self::cases(),
            self::ADMIN       => [self::ADMIN, self::USER],
            default           => [$this]
        };
    }
}
