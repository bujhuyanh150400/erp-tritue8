<?php

namespace App\Models;

use App\Constants\InvoiceStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherSalaryInvoice extends Model
{
    use HasBigIntId;

    protected $table = 'teacher_salary_invoices';

    protected $fillable = [
        'teacher_id',
        'class_id',
        'month',
        'total_sessions',
        'bonus',
        'penalty',
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
            'bonus' => 'decimal:0',
            'penalty' => 'decimal:0',
            'total_amount' => 'decimal:0',
            'paid_amount' => 'decimal:0',
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

    public function logs(): HasMany
    {
        return $this->hasMany(TeacherSalaryInvoiceLog::class, 'invoice_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getRemainingAmount(): int
    {
        return (int) ($this->total_amount - $this->paid_amount);
    }
}
