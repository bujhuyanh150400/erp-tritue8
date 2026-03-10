<?php

namespace App\Constants;

enum StaffRoleType: int
{
    case Receptionist = 0;

    public function label(): string
    {
        return match ($this) {
            self::Receptionist => 'Lễ tân',
        };
    }
}
