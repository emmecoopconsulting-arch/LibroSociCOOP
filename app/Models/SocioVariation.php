<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'socio_id',
    'verbale_id',
    'assemblea_id',
    'cda_assemblea_id',
    'decisione_cda',
    'approvazione_cda',
    'data_delibera_cda',
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
        'sospensione' => 'Sospensione dal lavoro (documento interno)',
    ];

    public const STATI = [
        'bozza' => 'In attesa di decisione CDA',
        'applicata' => 'Applicata',
        'documentata' => 'Documentata (solo documento interno)',
    ];

    public const TIPI_DOCUMENTAZIONE_INTERNA = ['sospensione'];

    public const DECISIONI_CDA = [
        'approvata' => 'Approvata',
        'respinta' => 'Respinta',
        'rinviata' => 'Rinviata',
    ];

    public const APPROVAZIONI_CDA = [
        'in_attesa' => 'In attesa CDA',
        'approvata' => 'Approvata',
        'respinta' => 'Respinta',
        'storica' => 'Variazione storica',
    ];

    protected function casts(): array
    {
        return [
            'data_verbale' => 'date',
            'data_delibera_cda' => 'date',
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

    public function cdaDecisions(): HasMany
    {
        return $this->hasMany(SocioVariationCdaDecision::class);
    }

    public function cdaAssemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class, 'cda_assemblea_id');
    }

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class);
    }
}
