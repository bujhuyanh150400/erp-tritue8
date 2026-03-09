<?php

namespace App\Core\Traits;

trait EnumHelper
{
    /**
     * Convert enum to options for select
     * @return array
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}
