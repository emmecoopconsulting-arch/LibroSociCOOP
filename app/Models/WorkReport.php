<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'protocollo',
    'data_intervento',
    'work_site_id',
    'work_site_name',
    'socio_ids',
    'operator_hours',
    'total_hours',
    'oggetto',
    'descrizione_lavoro',
    'rapportino_path',
    'note',
])]
class WorkReport extends Model
{
    protected function casts(): array
    {
        return [
            'data_intervento' => 'date',
            'socio_ids' => 'array',
            'operator_hours' => 'array',
            'total_hours' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkReport $report): void {
            $report->syncWorkSiteReference();
            $report->syncOperatorHours();
        });

        static::created(function (WorkReport $report): void {
            app(S3ArchiveService::class)->archiveWorkReportAttachment($report);
        });

        static::updated(function (WorkReport $report): void {
            if ($report->wasChanged('rapportino_path')) {
                app(S3ArchiveService::class)->archiveWorkReportAttachment($report);
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'work_site_id');
    }

    public function assignedSocios(): Collection
    {
        return Socio::query()
            ->whereIn('id', $this->socio_ids ?? [])
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();
    }

    public function operatorHoursBySocio(): Collection
    {
        $hoursBySocioId = collect($this->operator_hours ?? [])
            ->filter(fn (array $row): bool => filled($row['socio_id'] ?? null))
            ->groupBy(fn (array $row): int => (int) $row['socio_id'])
            ->map(fn (Collection $rows): float => $rows->sum(fn (array $row): float => (float) ($row['hours'] ?? 0)));

        if ($hoursBySocioId->isEmpty() && filled($this->socio_ids)) {
            $hoursBySocioId = collect($this->socio_ids)
                ->filter()
                ->mapWithKeys(fn ($socioId): array => [(int) $socioId => 0.0]);
        }

        if ($hoursBySocioId->isEmpty()) {
            return collect();
        }

        return Socio::query()
            ->whereIn('id', $hoursBySocioId->keys()->all())
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->map(fn (Socio $socio): array => [
                'socio' => $socio,
                'hours' => $hoursBySocioId->get($socio->id, 0.0),
            ]);
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
            $this->work_site_id = WorkSite::findOrCreateForLabel($this->work_site_name)?->id;
        }
    }

    private function syncOperatorHours(): void
    {
        $rows = collect($this->operator_hours ?? [])
            ->map(fn (array $row): array => [
                'socio_id' => filled($row['socio_id'] ?? null) ? (int) $row['socio_id'] : null,
                'hours' => filled($row['hours'] ?? null) ? round((float) $row['hours'], 2) : 0.0,
            ])
            ->filter(fn (array $row): bool => filled($row['socio_id']))
            ->groupBy('socio_id')
            ->map(fn (Collection $operatorRows, int $socioId): array => [
                'socio_id' => $socioId,
                'hours' => round($operatorRows->sum('hours'), 2),
            ])
            ->values();

        if ($rows->isEmpty() && filled($this->socio_ids)) {
            $rows = collect($this->socio_ids)
                ->filter()
                ->map(fn ($socioId): array => [
                    'socio_id' => (int) $socioId,
                    'hours' => 0.0,
                ])
                ->values();
        }

        $this->operator_hours = $rows->all();
        $this->socio_ids = $rows->pluck('socio_id')->unique()->values()->all();
        $this->total_hours = $rows->sum('hours');
    }
}
