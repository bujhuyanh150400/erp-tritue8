<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    use HasBigIntId;

    protected $table = 'class_enrollments';

    protected $fillable = [
        'class_id',
        'student_id',
        'fee_per_session',
        'fee_effective_from',
        'fee_effective_to',
        'enrolled_at',
        'left_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'fee_per_session' => 'decimal:0',
            'fee_effective_from' => 'date',
            'fee_effective_to' => 'date',
            'enrolled_at' => 'datetime',
            'left_at' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    /**
     * Resolve the effective fee for a given date.
     * Falls back to class base_fee_per_session if not set.
     */
    public function getEffectiveFeeForDate(\Carbon\Carbon $date): int
    {
        if ($this->fee_per_session === null) {
            return (int) $this->class->base_fee_per_session;
        }

        $withinFrom = $this->fee_effective_from === null
            || $date->gte($this->fee_effective_from);

        $withinTo = $this->fee_effective_to === null
            || $date->lte($this->fee_effective_to);

        if ($withinFrom && $withinTo) {
            return (int) $this->fee_per_session;
        }

        return (int) $this->class->base_fee_per_session;
    }
}
