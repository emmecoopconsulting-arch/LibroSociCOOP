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
    'work_site_name',
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
            $site->syncWorkSiteReference();

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

    public function displaySiteName(): string
    {
        return $this->work_site_name ?: ($this->site?->display_name ?? '');
    }

    private function syncWorkSiteReference(): void
    {
        if (blank($this->work_site_name) && $this->work_site_id) {
            $this->work_site_name = $this->site?->display_name ?? WorkSite::query()->find($this->work_site_id)?->display_name;
        }

        if (filled($this->work_site_name)) {
            $this->work_site_name = trim((string) $this->work_site_name);
            $this->work_site_id = WorkSite::idForLabel($this->work_site_name);
        }
    }
}
