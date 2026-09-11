<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'batch_id',
    'socio_id',
    'data_visita',
    'scadenza',
    'pdf_path',
    'note',
])]
class SocioMedicalVisit extends Model
{
    protected function casts(): array
    {
        return [
            'data_visita' => 'date',
            'scadenza' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (SocioMedicalVisit $visit) => $visit->syncDocument());

        static::created(function (SocioMedicalVisit $visit): void {
            $visit->archivePdfOnS3();
        });

        static::updated(function (SocioMedicalVisit $visit): void {
            if ($visit->wasChanged('pdf_path')) {
                $visit->archivePdfOnS3();
            }
        });
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SocioMedicalVisitBatch::class, 'batch_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(SocioDocument::class);
    }

    public function syncDocument(): void
    {
        if (blank($this->pdf_path)) {
            $this->document()->delete();

            return;
        }

        $this->document()->updateOrCreate([], [
            'socio_id' => $this->socio_id,
            'tipo' => 'visita_medica',
            'file_path' => $this->pdf_path,
            'data_rilascio' => $this->data_visita,
            'data_scadenza' => $this->scadenza,
            'note' => $this->note,
        ]);
    }

    public function scopeLatestForSocios(Builder $query): Builder
    {
        return $query->whereNotExists(function ($newer): void {
            $newer->selectRaw('1')->from('socio_medical_visits as newer')
                ->whereColumn('newer.socio_id', 'socio_medical_visits.socio_id')
                ->where(function ($dates): void {
                    $dates->whereColumn('newer.data_visita', '>', 'socio_medical_visits.data_visita')
                        ->orWhere(function ($tie): void {
                            $tie->whereColumn('newer.data_visita', 'socio_medical_visits.data_visita')
                                ->whereColumn('newer.id', '>', 'socio_medical_visits.id');
                        });
                });
        });
    }

    private function archivePdfOnS3(): void
    {
        $this->loadMissing('socio');

        if ($this->socio && filled($this->pdf_path)) {
            app(S3ArchiveService::class)->archiveSocioLocalFile($this->socio, 'visite-mediche', $this->pdf_path);
        }
    }
}
