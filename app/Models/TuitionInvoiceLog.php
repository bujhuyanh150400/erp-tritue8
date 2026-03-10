<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuitionInvoiceLog extends Model
{
    use HasBigIntId;

    protected $table = 'tuition_invoice_logs';

    protected $fillable = [
        'invoice_id',
        'amount',
        'paid_at',
        'note',
        'is_cancelled',
        'cancelled_at',
        'cancel_reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:0',
            'paid_at'      => 'datetime',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class, 'invoice_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
