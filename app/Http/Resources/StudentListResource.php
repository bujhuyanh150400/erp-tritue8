<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Phải edge load user trước
        $user = $this->user;

        return [
            'id' => (string) $this->id,
            'user' => [
                'id' => (string) $user->id,
                'is_active' => (bool) $user->is_active,
                'role' => $user->role,
            ],
            'full_name' => $this->full_name,
            'dob' => $this->dob,
            'gender' => $this->gender,
            'grade_level' => $this->grade_level,
            'parent_name' => $this->parent_name,
            'parent_phone' => $this->parent_phone,
            'address' => $this->address,
            'note' => $this->note ?? null,
        ];
    }
}
