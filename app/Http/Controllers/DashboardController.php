<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;

class DashboardController extends BaseController
{
    public function __construct() {}

    public function index()
    {
        return $this->rendering('dashboard');
    }
}
