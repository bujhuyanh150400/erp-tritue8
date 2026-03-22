<?php

namespace App\Models;

use App\Constants\DayOfWeek;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassScheduleTemplate extends Model
{
    use HasBigIntId;

    protected $table = 'class_schedule_templates';

    protected $fillable = [
        'class_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_id',
        'teacher_id',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduleInstances(): HasMany
    {
        return $this->hasMany(ScheduleInstance::class, 'template_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->end_date === null || $this->end_date->isFuture();
    }

    public function scopeCurrentlyActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('class_schedule_templates.end_date')
                ->orWhere('class_schedule_templates.end_date', '>=', now());
        });
    }
}
