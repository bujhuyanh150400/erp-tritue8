<?php


namespace App\Http\Requests;

use App\Core\Requests\ListRequest;

class ListStaffRequest extends ListRequest
{
    protected array $allowedFilters = [
        'keyword',
        'full_name',
    ];
}
