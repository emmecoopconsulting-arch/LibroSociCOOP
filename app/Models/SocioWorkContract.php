<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'socio_id',
    'verbale_id',
    'tipo_contratto',
    'data_inizio',
    'data_fine',
    'ore_settimanali',
    'stato',
    'note',
])]
class SocioWorkContract extends Model
{
    public const TIPI_CONTRATTO = [
        'determinato' => 'Determinato',
        'indeterminato' => 'Indeterminato',
    ];

    public const STATI = [
        'attivo' => 'Attivo',
        'cessato' => 'Cessato',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio' => 'date',
            'data_fine' => 'date',
            'ore_settimanali' => 'decimal:2',
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
}
