<?php

namespace App\Models;

use App\Services\WorkOrderScheduleValidator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_order_site_id',
    'socio_id',
])]
class WorkSiteAssignment extends Model
{
    protected static function booted(): void
    {
        static::saving(function (WorkSiteAssignment $assignment): void {
            app(WorkOrderScheduleValidator::class)->validateAssignment($assignment);
        });
    }

    public function orderSite(): BelongsTo
    {
        return $this->belongsTo(WorkOrderSite::class, 'work_order_site_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
