<?php

namespace App\Models;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * Named SchoolClass to avoid collision with PHP's reserved word 'Class'.
 * Maps to the `classes` table.
 */
class SchoolClass extends Model
{
    use HasBigIntId;

    protected $table = 'classes';

    protected $fillable = [
        'code',
        'name',
        'subject_id',
        'teacher_id',
        'grade_level',
        'base_fee_per_session',
        'max_students',
        'status',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
            'status' => ClassStatus::class,
            'base_fee_per_session' => 'decimal:0',
            'start_at' => 'date',
            'end_at' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id')
            ->whereNull('left_at');
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ClassScheduleTemplate::class, 'class_id');
    }

    public function activeTemplate(): HasMany
    {
        return $this->hasMany(ClassScheduleTemplate::class, 'class_id')
            ->whereNull('end_date');
    }

    public function scheduleInstances(): HasMany
    {
        return $this->hasMany(ScheduleInstance::class, 'class_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
    }

    public function tuitionInvoices(): HasMany
    {
        return $this->hasMany(TuitionInvoice::class, 'class_id');
    }

    public function teacherSalaryInvoices(): HasMany
    {
        return $this->hasMany(TeacherSalaryInvoice::class, 'class_id');
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class, 'class_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getActiveStudentCount(): int
    {
        return $this->activeEnrollments()->count();
    }

    public function isAtCapacity(): bool
    {
        return $this->max_students > 0
            && $this->getActiveStudentCount() >= $this->max_students;
    }
}
