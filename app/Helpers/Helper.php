<?php

namespace App\Helpers;

final class Helper
{
    public static function refreshPage(): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        return redirect(request()->header('Referer'));
    }
}
