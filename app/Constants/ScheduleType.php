<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasLabel;

enum ScheduleType: int implements HasLabel
{
    use EnumHelper;
    case Main    = 0; // Lịch chính
    case Makeup  = 1; // Học bù
    case Extra   = 2; // Tăng cường
    case Holiday = 3; // Nghỉ

    public function label(): string
    {
        return match($this) {
            self::Main    => 'Lịch chính',
            self::Makeup  => 'Học bù',
            self::Extra   => 'Tăng cường',
            self::Holiday => 'Nghỉ',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Main    => Color::Blue[200],
            self::Makeup  => Color::Orange[200],
            self::Extra   => Color::Purple[200],
            self::Holiday => Color::Red[200],
            default => Color::Gray[200],
        };
    }

    public function colorFilament(): string
    {
        return match($this) {
            self::Main    => 'blue',
            self::Makeup  => 'orange',
            self::Extra   => 'purple',
            self::Holiday => 'red',
            default => 'gray',
        };
    }
}
