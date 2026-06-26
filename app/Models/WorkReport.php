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
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkReport $report): void {
            $report->syncWorkSiteReference();
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
