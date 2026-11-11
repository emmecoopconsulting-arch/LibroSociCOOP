<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'socio_variation_id',
    'assemblea_id',
    'decisione',
    'data_delibera',
])]
class SocioVariationCdaDecision extends Model
{
    protected function casts(): array
    {
        return ['data_delibera' => 'date'];
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(SocioVariation::class, 'socio_variation_id');
    }

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class);
    }
}
