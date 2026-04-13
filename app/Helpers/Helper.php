<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

final class Helper
{
    public static function refreshPage(): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        return redirect(request()->header('Referer'));
    }

    /**
     * Lấy ngày kết thúc mặc định (vô hạn)
     */
    public static function getEndlessDateDefault(): Carbon
    {
        return Carbon::make("2099-12-31");
    }
}
