<?php


namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherItemResource extends JsonResource
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
            'id' => (string)$this->id,
            'user_id' => (string)$this->user_id,
            'user_name' => $user->username,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email ?? null,
            'address' => $this->address ?? null,
            'bank_name' => $this->bank_name ?? null,
            'bank_account_number' => $this->bank_account_number ?? null,
            'bank_account_holder' => $this->bank_account_holder ?? null,
            'status' => $this->status,
            'joined_at' => $this->joined_at ? Carbon::parse($this->joined_at)->format('Y-m-d') : null,
        ];
    }
}
