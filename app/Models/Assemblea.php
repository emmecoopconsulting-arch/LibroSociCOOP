<?php

namespace App\Models;

use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'data_assemblea',
    'tipo_assemblea',
    'titolo',
    'note',
    'presidente',
    'segretario',
    'luogo',
    'modalita',
    'stato',
    'started_at',
    'closed_at',
    'file_path',
    'generato_il',
])]
class Assemblea extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Assemblea $assemblea): void {
            if ($assemblea->wasChanged('file_path')) {
                app(S3ArchiveService::class)->archiveAssemblyPdf($assemblea);
            }
        });
    }

    public const TIPI_ASSEMBLEA = [
        'soci' => 'Assemblea dei soci',
        'cda' => 'Consiglio di amministrazione',
    ];

    public const STATI = [
        'bozza' => 'Bozza',
        'in_corso' => 'In corso',
        'chiusa' => 'Chiusa',
        'generata' => 'Generata',
        'annullata' => 'Annullata',
    ];

    public const MODALITA = [
        'presenza' => 'In presenza',
        'online' => 'Online',
        'mista' => 'Mista',
    ];

    protected $table = 'assemblee';

    protected function casts(): array
    {
        return [
            'data_assemblea' => 'date',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'generato_il' => 'datetime',
        ];
    }

    public function variations(): HasMany
    {
        return $this->hasMany(SocioVariation::class);
    }

    public function cdaDecisions(): HasMany
    {
        return $this->hasMany(SocioVariationCdaDecision::class);
    }

    public function cdaVariations(): HasMany
    {
        return $this->hasMany(SocioVariation::class, 'cda_assemblea_id');
    }

    public function presenze(): HasMany
    {
        return $this->hasMany(AssembleaPresenza::class);
    }

    public function puntiOdg(): HasMany
    {
        return $this->hasMany(AssembleaPuntoOdg::class)->orderBy('ordine');
    }
}
