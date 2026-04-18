<?php

namespace App\Constants;

use App\Core\Traits\EnumHelper;
use Filament\Support\Contracts\HasLabel;

enum SalaryType: int implements HasLabel
{
    use EnumHelper;
    case Session = 1; // Theo ca
    case Fixed  = 2; // Cố định tháng

    public function label(): string
    {
        return match($this) {
            self::Session => 'Theo ca',
            self::Fixed  => 'Cố định tháng',
        };
    }
}
