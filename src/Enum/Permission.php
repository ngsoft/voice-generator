<?php

namespace Enum;

enum Permission: int
{
    case None  = 0;
    case Read  = 4;
    case Write = 6;
}
