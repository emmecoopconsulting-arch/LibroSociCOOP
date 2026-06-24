<?php

namespace App\Models;

use App\Services\WorkOrderScheduleValidator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'work_order_id',
    'work_vehicle_id',
    'nome',
    'luogo',
    'orario_inizio',
    'orario_fine',
    'note',
])]
class WorkSite extends Model
{
    protected static function booted(): void
    {
        static::saving(function (WorkSite $site): void {
            app(WorkOrderScheduleValidator::class)->validateSite($site);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(WorkVehicle::class, 'work_vehicle_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkSiteAssignment::class);
    }
}
