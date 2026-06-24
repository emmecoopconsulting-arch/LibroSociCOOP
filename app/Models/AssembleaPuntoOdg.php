<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assemblea_id',
    'ordine',
    'titolo',
    'descrizione',
    'discussione',
    'esito',
    'voti_favorevoli',
    'voti_contrari',
    'astenuti',
])]
class AssembleaPuntoOdg extends Model
{
    public const ESITI = [
        'da_discutere' => 'Da discutere',
        'discusso' => 'Discusso',
        'approvato' => 'Approvato',
        'respinto' => 'Respinto',
        'rinviato' => 'Rinviato',
    ];

    protected $table = 'assemblea_punti_odg';

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class);
    }
}
