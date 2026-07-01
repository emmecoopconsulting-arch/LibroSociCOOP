<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'socio_id',
    'payroll_distribution_id',
    'tipo',
    'periodo_riferimento',
    'numero_documento',
    'data_rilascio',
    'data_scadenza',
    'file_path',
    'note',
])]
class SocioDocument extends Model
{
    public const TIPI = [
        'cie' => 'CIE',
        'carta_identita' => 'Carta identita',
        'codice_fiscale' => 'Codice fiscale',
        'permesso_soggiorno' => 'Permesso di soggiorno',
        'patente' => 'Patente',
        'busta_paga' => 'Busta paga',
        'altro' => 'Altro documento',
    ];

    protected function casts(): array
    {
        return [
            'data_rilascio' => 'date',
            'data_scadenza' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (SocioDocument $document): void {
            $document->archiveOnS3();
        });

        static::updated(function (SocioDocument $document): void {
            if ($document->wasChanged('file_path')) {
                $document->archiveOnS3();
            }
        });
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function payrollDistribution(): BelongsTo
    {
        return $this->belongsTo(PayrollDistribution::class);
    }

    private function archiveOnS3(): void
    {
        $this->loadMissing('socio');

        if ($this->socio && filled($this->file_path)) {
            app(S3ArchiveService::class)->archiveSocioDocument($this);
        }
    }
}
