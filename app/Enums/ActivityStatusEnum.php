<?php

namespace App\Enums;

enum ActivityStatusEnum: string 
{
    case USER_ACTIVE = 'active';
    case USER_AWAY = 'away';
}
