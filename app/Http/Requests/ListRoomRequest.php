<?php


namespace App\Http\Requests;

use App\Core\Requests\ListRequest;

class ListRoomRequest extends ListRequest
{
    protected array $allowedFilters = [
        'keyword',
        'status',
    ];
}
