<?php

namespace App\Models;

use App\Constants\ShiftStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    use HasBigIntId;

    protected $table = 'staff_shifts';

    protected $fillable = [
        'staff_id',
        'shift_date',
        'check_in_time',
        'check_out_time',
        'total_hours',
        'hourly_rate_snapshot',
        'total_salary',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'total_hours' => 'decimal:2',
            'hourly_rate_snapshot' => 'decimal:0',
            'total_salary' => 'decimal:0',
            'status' => ShiftStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->check_out_time === null;
    }

    public function isLocked(): bool
    {
        return $this->status === ShiftStatus::Locked;
    }

    public function calculateHours(): float
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return 0.0;
        }
        return round(
            $this->check_in_time->diffInMinutes($this->check_out_time) / 60,
            2
        );
    }
}
