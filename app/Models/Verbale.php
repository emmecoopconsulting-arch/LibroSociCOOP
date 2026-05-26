<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'socio_id',
    'tipo',
    'stato',
    'titolo',
    'data_verbale',
    'file_path',
    'generato_il',
])]
class Verbale extends Model
{
    public const TIPI = [
        'ammissione' => 'Ammissione socio',
        'recesso' => 'Recesso socio',
        'esclusione' => 'Esclusione socio',
        'sospensione' => 'Sospensione socio',
        'variazione_contratto' => 'Variazione contratto',
        'proroga_contratto' => 'Proroga contratto',
        'trasformazione_indeterminato' => 'Trasformazione a indeterminato',
        'variazione_ore' => 'Variazione ore settimanali',
        'cessazione_rapporto' => 'Cessazione rapporto',
    ];

    public const STATI = [
        'da_generare' => 'Da generare',
        'generato' => 'Generato',
    ];

    protected function casts(): array
    {
        return [
            'data_verbale' => 'date',
            'generato_il' => 'datetime',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function variation(): HasOne
    {
        return $this->hasOne(SocioVariation::class);
    }
}
