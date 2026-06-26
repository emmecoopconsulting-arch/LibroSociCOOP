<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
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

    private function archivePdfOnS3(): void
    {
        $this->loadMissing('socio');

        if ($this->socio && filled($this->pdf_path)) {
            app(S3ArchiveService::class)->archiveSocioLocalFile($this->socio, 'visite-mediche', $this->pdf_path);
        }
    }
}
