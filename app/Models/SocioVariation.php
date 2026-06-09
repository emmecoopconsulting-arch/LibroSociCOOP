<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'socio_id',
    'verbale_id',
    'assemblea_id',
    'tipo',
    'stato',
    'data_verbale',
    'data_effetto',
    'tipo_contratto',
    'data_inizio',
    'data_fine',
    'ore_settimanali',
    'snapshot_precedente',
    'snapshot_successivo',
    'note',
])]
class SocioVariation extends Model
{
    public const TIPI = [
        'variazione_contratto' => 'Variazione contratto',
        'proroga_contratto' => 'Proroga contratto determinato',
        'trasformazione_indeterminato' => 'Trasformazione a indeterminato',
        'variazione_ore' => 'Variazione ore settimanali',
        'cessazione_rapporto' => 'Cessazione rapporto di lavoro',
        'recesso' => 'Recesso socio',
        'esclusione' => 'Esclusione socio',
        'sospensione' => 'Sospensione socio',
    ];

    public const STATI = [
        'bozza' => 'Bozza',
        'applicata' => 'Applicata',
    ];

    protected function casts(): array
    {
        return [
            'data_verbale' => 'date',
            'data_effetto' => 'date',
            'data_inizio' => 'date',
            'data_fine' => 'date',
            'ore_settimanali' => 'decimal:2',
            'snapshot_precedente' => 'array',
            'snapshot_successivo' => 'array',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function verbale(): BelongsTo
    {
        return $this->belongsTo(Verbale::class);
    }

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class);
    }
}
