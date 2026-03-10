<?php

namespace App\Models;

use App\Constants\InvoiceStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TuitionInvoice extends Model
{
    use HasBigIntId;

    protected $table = 'tuition_invoices';

    protected $fillable = [
        'invoice_number',
        'student_id',
        'class_id',
        'month',
        'total_sessions',
        'attended_sessions',
        'total_study_fee',
        'discount_amount',
        'previous_debt',
        'total_amount',
        'paid_amount',
        'status',
        'is_locked',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'is_locked' => 'boolean',
            'total_study_fee' => 'decimal:0',
            'discount_amount' => 'decimal:0',
            'previous_debt' => 'decimal:0',
            'total_amount' => 'decimal:0',
            'paid_amount' => 'decimal:0',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TuitionInvoiceLog::class, 'invoice_id');
    }

    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'invoice_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getRemainingAmount(): int
    {
        return (int) ($this->total_amount - $this->paid_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function canEdit(): bool
    {
        return ! $this->is_locked;
    }
}
