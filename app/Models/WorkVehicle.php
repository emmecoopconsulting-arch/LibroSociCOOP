<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome',
    'tipo',
    'targa',
    'attivo',
    'note',
])]
class WorkVehicle extends Model
{
    public const TIPI = [
        'proprio' => 'Proprio',
        'ditta' => 'Della ditta',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(WorkSite::class);
    }

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where('attivo', true);
    }

    public function getDescrizioneAttribute(): string
    {
        return trim($this->nome.($this->targa ? " ({$this->targa})" : ''));
    }
}
