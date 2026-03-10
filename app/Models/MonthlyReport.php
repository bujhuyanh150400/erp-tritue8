<?php

namespace App\Models;

use App\Constants\ReportStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReport extends Model
{
    use HasBigIntId;

    protected $table = 'monthly_reports';
    protected $fillable = [
        'teacher_id',
        'class_id',
        'student_id',
        'month',
        'status',
        'content',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
