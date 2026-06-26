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
}
