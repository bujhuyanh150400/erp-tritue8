<?php

namespace App\Core\Controller;

use App\Core\Traits\HandleApi;
use App\Core\Traits\HandleInertia;
use App\Core\Traits\ToastInertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class BaseController
{
    use AuthorizesRequests, HandleApi, HandleInertia, ToastInertia, ValidatesRequests;
}
