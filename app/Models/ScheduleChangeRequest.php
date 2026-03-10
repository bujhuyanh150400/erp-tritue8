<?php

namespace App\Models;

use App\Constants\ScheduleChangeStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChangeRequest extends Model
{
    use HasBigIntId;

    protected $table = 'schedule_change_requests';

    protected $fillable = [
        'schedule_instance_id',
        'requested_by',
        'proposed_date',
        'proposed_start_time',
        'proposed_end_time',
        'proposed_room_id',
        'proposed_teacher_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduleChangeStatus::class,
            'proposed_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function scheduleInstance(): BelongsTo
    {
        return $this->belongsTo(ScheduleInstance::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'requested_by');
    }

    public function proposedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'proposed_room_id');
    }

    public function proposedTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'proposed_teacher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ScheduleChangeStatus::Pending;
    }
}
