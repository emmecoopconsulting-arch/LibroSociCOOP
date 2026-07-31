<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'subject', 'body', 'attachment_path', 'attachment_name',
    'attachment_description', 'filters', 'status', 'recipient_count',
    'sent_count', 'failed_count', 'skipped_count', 'sent_at',
])]
class MassCommunication extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MassCommunicationDelivery::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SocioDocument::class);
    }
}
