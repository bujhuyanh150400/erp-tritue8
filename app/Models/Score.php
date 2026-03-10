<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    use HasBigIntId;

    protected $table = 'scores';

    protected $fillable = [
        'attendance_record_id',
        'exam_slot',
        'exam_name',
        'score',
        'max_score',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getPercentage(): float
    {
        if ($this->max_score == 0) {
            return 0;
        }

        return round(($this->score / $this->max_score) * 100, 1);
    }
}
