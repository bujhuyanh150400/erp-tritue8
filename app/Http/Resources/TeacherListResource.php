<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        return [
            'id' => (string)$this->id,
            'user' => [
                'id' => (string)$user->id,
                'is_active' => (bool)$user->is_active,
                'role' => $user->role,
            ],
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email ?? null,
            'status' => $this->status,
            'joined_at' => $this->joined_at,
            'address' => $this->address ?? null,
        ];
    }
}
