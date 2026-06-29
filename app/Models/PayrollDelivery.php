<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_distribution_id', 'socio_id', 'email', 'attachment_path',
    'status', 'error', 'sent_at',
])]
class PayrollDelivery extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(PayrollDistribution::class, 'payroll_distribution_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
