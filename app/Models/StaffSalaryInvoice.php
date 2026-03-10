<?php

namespace App\Models;

use App\Constants\InvoiceStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffSalaryInvoice extends Model
{
    use HasBigIntId;

    protected $table = 'staff_salary_invoices';

    protected $fillable = [
        'staff_id',
        'month',
        'base_salary',
        'bonus',
        'penalty',
        'advance_amount',
        'total_amount',
        'paid_amount',
        'status',
        'is_locked',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status'        => InvoiceStatus::class,
            'is_locked'     => 'boolean',
            'base_salary'   => 'decimal:0',
            'bonus'         => 'decimal:0',
            'penalty'       => 'decimal:0',
            'advance_amount'=> 'decimal:0',
            'total_amount'  => 'decimal:0',
            'paid_amount'   => 'decimal:0',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StaffSalaryInvoiceLog::class, 'invoice_id');
    }

    public function getRemainingAmount(): int
    {
        return (int) ($this->total_amount - $this->paid_amount);
    }
}
