<?php

namespace App\Core\Controller;

use App\Core\Traits\HandleApi;
use App\Core\Traits\HandleInertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class BaseController
{
    use AuthorizesRequests, HandleApi, HandleInertia,  ValidatesRequests;
}
