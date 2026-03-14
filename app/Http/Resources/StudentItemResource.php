<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'user_name' => $user->username,
            'full_name' => $this->full_name,
            'dob' => Carbon::parse($this->dob)->format('Y-m-d'),
            'gender' => $this->gender,
            'grade_level' => $this->grade_level,
            'parent_name' => $this->parent_name,
            'parent_phone' => $this->parent_phone,
            'address' => $this->address,
            'note' => $this->note ?? null,
        ];
    }
}
