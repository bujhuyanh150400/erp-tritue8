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
use Illuminate\Support\Carbon;

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

    /**
     * Buổi học này (buổi nghỉ) đã có lịch bù nào TRỎ TỚI nó chưa?
     */
    public function makeupInstance()
    {
        // Quan hệ 1-1: Một buổi nghỉ chỉ có 1 buổi bù
        return $this->hasOne(ScheduleInstance::class, 'linked_makeup_for');
    }

    /**
     * Buổi học này (buổi bù) đang TRỎ VỀ buổi nghỉ nào?
     */
    public function originalInstance()
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

    /**
     * Check xem có phải ngày nghỉ hay không
     * @return bool
     */
    public function isDayOff(): bool
    {
        return $this->schedule_type === ScheduleType::Holiday || $this->status === ScheduleStatus::Cancelled;
    }

    /**
     * Check xem đã điểm danh hay chưa
     * @return bool
     */
    public function hasAttendance(): bool
    {
        if ($this->relationLoaded('attendanceSession')) {
            return $this->attendanceSession !== null;
        }
        return $this->attendanceSession()->exists();
    }

    /**
     * Check xem có quá hạn và chưa điểm danh hay không
     * @return bool
     */
    public function isOverdueWithoutAttendance(): bool
    {
        $date = Carbon::parse($this->date);

        return $date->isPast()
            && !$date->isToday()
            && $this->status === ScheduleStatus::Upcoming
            && !$this->hasAttendance();
    }

    /**
     * Check xem có thể sửa lịch học hay không
     * @return bool
     */
    public function canEditingInstance(): bool
    {
        return !$this->isDayOff() && !$this->hasAttendance();
    }

    /**
     * Check xem có thể tạo lịch học thay thế hay không
     * @return bool
     */
    public function canMakeMarkupInstance(): bool
    {
        $hasMakeup = $this->relationLoaded('makeupInstance')
            ? $this->makeupInstance !== null
            : $this->makeupInstance()->exists();

        return $this->isDayOff()               // 1. Phải là ngày nghỉ
            && !$this->hasAttendance()         // 2. Chưa có dữ liệu điểm danh
            && empty($this->linked_makeup_for) // 3. Bản thân buổi này KHÔNG PHẢI là một buổi đi bù cho thằng khác
            && !$hasMakeup; // 4. QUAN TRỌNG: Chưa có thằng nào đi bù cho nó
    }

}
