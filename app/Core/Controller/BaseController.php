<?php

namespace App\Core\Controller;

use App\Core\Traits\HandleApi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class BaseController
{
    use AuthorizesRequests, ValidatesRequests, HandleApi;

}
