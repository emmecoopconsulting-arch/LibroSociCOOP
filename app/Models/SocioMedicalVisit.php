<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'socio_id',
    'data_visita',
    'scadenza',
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

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
