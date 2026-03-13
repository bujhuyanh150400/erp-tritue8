<?php

namespace App\Http\Requests;

use App\Core\Requests\ListRequest;
use Illuminate\Foundation\Http\FormRequest;

class ListStudentRequest extends ListRequest
{
    public function authorize(): bool
    {
        return true;
    }

}
