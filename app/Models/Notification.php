<?php

namespace App\Models;

use App\Constants\NotificationChannel;
use App\Constants\NotificationSendStatus;
use App\Constants\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'type',
        'is_read',
        'read_at',
        'channel',
        'send_status',
        'sent_at',
        'is_urgent',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'send_status' => NotificationSendStatus::class,
            'is_read' => 'boolean',
            'is_urgent' => 'boolean',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Polymorphic reference – links to schedule_instances, tuition_invoices, etc. */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
