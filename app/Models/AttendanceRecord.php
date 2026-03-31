<?php

namespace App\Models;

use App\Constants\AttendanceStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasBigIntId;

    protected $table = 'attendance_records';

    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'check_in_time',
        'is_fee_counted',
        'teacher_comment',
        'reason_absent',
        'private_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'is_fee_counted' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'attendance_record_id', 'id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isPresent(): bool
    {
        return in_array($this->status, [
            AttendanceStatus::Present,
            AttendanceStatus::Late,
        ]);
    }

}
