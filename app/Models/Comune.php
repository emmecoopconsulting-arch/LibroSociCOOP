<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'progressivo',
    'denominazione',
    'ripartizione_geografica',
    'regione',
    'provincia_unita_territoriale',
    'codice_catastale',
])]
class Comune extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Comune $comune): void {
            $comune->codice_catastale = strtoupper((string) $comune->codice_catastale);
        });
    }

    public function sociNati(): HasMany
    {
        return $this->hasMany(Socio::class, 'comune_nascita_id');
    }

    public function sociResidenti(): HasMany
    {
        return $this->hasMany(Socio::class, 'comune_residenza_id');
    }
}
