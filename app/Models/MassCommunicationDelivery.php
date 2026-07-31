<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mass_communication_id', 'socio_id', 'email', 'status', 'error', 'sent_at'])]
class MassCommunicationDelivery extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(MassCommunication::class, 'mass_communication_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
