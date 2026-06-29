<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'original_name', 'source_path', 'period', 'status', 'total_pages',
    'sent_count', 'failed_count', 'error', 'sent_at',
])]
class PayrollDistribution extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PayrollDistributionPage::class)->orderBy('page_number');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PayrollDelivery::class);
    }
}
