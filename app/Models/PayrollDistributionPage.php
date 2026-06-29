<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_distribution_id', 'socio_id', 'page_number', 'page_path',
    'match_confidence', 'match_reason', 'ocr_text',
])]
class PayrollDistributionPage extends Model
{
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(PayrollDistribution::class, 'payroll_distribution_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
