<?php

namespace App\Models;

use App\Constants\AttendanceSessionStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasBigIntId;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'schedule_instance_id',
        'class_id',
        'teacher_id',
        'session_date',
        'lesson_content',
        'homework',
        'next_session_note',
        'general_note',
        'status',
        'completed_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'status' => AttendanceSessionStatus::class,
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function scheduleInstance(): BelongsTo
    {
        return $this->belongsTo(ScheduleInstance::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class, 'session_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === AttendanceSessionStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === AttendanceSessionStatus::Completed;
    }

    public function isLocked(): bool
    {
        return $this->status === AttendanceSessionStatus::Locked;
    }

    public function canEdit(): bool
    {
        return ! $this->isLocked();
    }
}
