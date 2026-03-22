<?php

namespace App\Models;

use App\Constants\RoomStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasBigIntId;

    protected $table = 'rooms';

    protected $fillable = [
        'name',
        'capacity',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ClassScheduleTemplate::class);
    }

    public function scheduleInstances(): HasMany
    {
        return $this->hasMany(ScheduleInstance::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === RoomStatus::Active;
    }
}
