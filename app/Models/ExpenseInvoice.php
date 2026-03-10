<?php

namespace App\Models;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseInvoice extends Model
{
    use HasBigIntId;

    protected $table = 'expense_invoices';

    protected $fillable = [
        'category_id',
        'title',
        'status',
        'month',
        'amount',
        'paid_at',
        'note',
        'changed_by',
        'payment_method',
        'created_by',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:0',
            'paid_at' => 'datetime',
            'is_recurring' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
