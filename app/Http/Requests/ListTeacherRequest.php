<?php


namespace App\Http\Requests;

use App\Core\Requests\ListRequest;
use Illuminate\Foundation\Http\FormRequest;

class ListTeacherRequest extends ListRequest
{
    protected array $allowedFilters = [
        'keyword',
        'full_name',
    ];
}
