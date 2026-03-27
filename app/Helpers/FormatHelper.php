<?php

namespace App\Helpers;

final class FormatHelper
{
    /**
     * Định dạng giá tiền thành chuỗi có dấu chấm ngăn cách hàng nghìn và dấu ₫ ở cuối.
     * @param float $price
     * @return string
     */
    public static function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', '.');
    }
}
