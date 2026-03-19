<?php

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NavigationGroup implements HasLabel
{
    case USER;

    case EDUCATION;

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::USER => 'Người dùng',
            self::EDUCATION => 'Học vụ',
        };
    }
}
