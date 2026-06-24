<?php

namespace App\Models;

use App\Services\WorkOrderScheduleValidator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'work_order_id',
    'work_site_id',
    'work_vehicle_id',
    'socio_ids',
    'orario_inizio',
    'orario_fine',
    'note',
])]
class WorkOrderSite extends Model
{
    protected static function booted(): void
    {
        static::saving(function (WorkOrderSite $site): void {
            app(WorkOrderScheduleValidator::class)->validateOrderSite($site);
        });
    }

    protected function casts(): array
    {
        return [
            'socio_ids' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'work_site_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(WorkVehicle::class, 'work_vehicle_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkSiteAssignment::class);
    }

    public function assignedSocios(): Collection
    {
        return Socio::query()
            ->whereIn('id', $this->socio_ids ?? [])
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();
    }
}
