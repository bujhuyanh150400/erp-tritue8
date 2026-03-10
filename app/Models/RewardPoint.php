<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardPoint extends Model
{
    use HasBigIntId;

    protected $table = 'reward_points';

    protected $fillable = [
        'student_id',
        'session_id',
        'amount',
        'reason',
        'awarded_by',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isDeduction(): bool
    {
        return $this->amount < 0;
    }
}
