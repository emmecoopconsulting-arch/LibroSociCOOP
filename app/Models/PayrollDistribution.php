<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'original_name', 'source_path', 'period', 'status', 'total_pages',
    'sent_count', 'failed_count', 'skipped_count', 'error', 'sent_at',
])]
class PayrollDistribution extends Model
{
    protected static function booted(): void
    {
        static::created(function (PayrollDistribution $distribution): void {
            app(S3ArchiveService::class)->archivePayrollSource($distribution);
        });

        static::updated(function (PayrollDistribution $distribution): void {
            if ($distribution->wasChanged('source_path')) {
                app(S3ArchiveService::class)->archivePayrollSource($distribution);
            }
        });
    }

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

    public function documents(): HasMany
    {
        return $this->hasMany(SocioDocument::class);
    }
}
