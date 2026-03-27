<?php

namespace App\Models;

use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScheduleInstance extends Model
{
    use HasBigIntId;

    protected $table = 'schedule_instances';
    protected $fillable = [
        'class_id',
        'template_id',
        'date',
        'start_time',
        'end_time',
        'room_id',
        'teacher_id',
        'original_teacher_id',
        'teacher_salary_snapshot',
        'custom_salary',
        'schedule_type',
        'status',
        'linked_makeup_for',
        'fee_type',
        'custom_fee_per_session',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'schedule_type' => ScheduleType::class,
            'status' => ScheduleStatus::class,
            'fee_type' => FeeType::class,
            'teacher_salary_snapshot' => 'decimal:0',
            'custom_salary' => 'decimal:0',
            'custom_fee_per_session' => 'decimal:0',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** The enrollments for this class. */
    public function classEnrollments(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class, 'class_id','class_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleTemplate::class, 'template_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function originalTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'original_teacher_id');
    }

    /** The original session this one is making up for. */
    public function makeupFor(): BelongsTo
    {
        return $this->belongsTo(ScheduleInstance::class, 'linked_makeup_for');
    }

    public function attendanceSession(): HasOne
    {
        return $this->hasOne(AttendanceSession::class, 'schedule_instance_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeRequests()
    {
        return $this->hasMany(ScheduleChangeRequest::class, 'schedule_instance_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isSubstitute(): bool
    {
        return $this->teacher_id !== $this->original_teacher_id;
    }

    public function getEffectiveSalary(): int
    {
        return (int)($this->custom_salary ?? $this->teacher_salary_snapshot);
    }
}
