<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffListResource extends JsonResource
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
            'role_type' => $this->role_type,
            'status' => $this->status,
            'joined_at' => $this->joined_at,
            'bank_name' => $this->bank_name ?? null,
        ];
    }
}
