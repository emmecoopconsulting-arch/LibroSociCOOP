<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'protocollo',
    'data_intervento',
    'work_order_id',
    'work_site_id',
    'work_site_name',
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
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkReport $report): void {
            $report->syncWorkSiteReference();
        });

        static::creating(function (WorkReport $report): void {
            if (filled($report->protocollo)) {
                return;
            }

            $year = (int) (filled($report->data_intervento)
                ? CarbonImmutable::parse($report->data_intervento)->format('Y')
                : now()->format('Y'));
            $latest = static::query()
                ->where('protocollo', 'like', "RINT-{$year}-%")
                ->orderByDesc('protocollo')
                ->value('protocollo');

            $nextNumber = $latest
                ? ((int) str($latest)->afterLast('-')->toString()) + 1
                : 1;

            $report->protocollo = sprintf('RINT-%d-%04d', $year, $nextNumber);
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'work_site_id');
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
