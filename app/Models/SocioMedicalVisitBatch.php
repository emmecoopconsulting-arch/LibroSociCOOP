<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'data_visita',
    'note',
])]
class SocioMedicalVisitBatch extends Model
{
    protected function casts(): array
    {
        return [
            'data_visita' => 'date',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SocioMedicalVisit::class, 'batch_id');
    }
}
