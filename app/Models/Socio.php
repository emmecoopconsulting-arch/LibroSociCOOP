<?php

namespace App\Models;

use App\Services\CodiceFiscaleParser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'codice_socio',
    'tipologia',
    'nome',
    'cognome',
    'codice_fiscale',
    'data_nascita',
    'luogo_nascita',
    'comune_nascita_id',
    'comune_residenza_id',
    'comune_residenza',
    'indirizzo',
    'telefono',
    'email',
    'data_ammissione',
    'verbale_cda_path',
    'stato',
    'data_uscita',
    'quota_sociale',
    'capitale_versato',
    'note',
])]
class Socio extends Model
{
    use LogsActivity, SoftDeletes;

    public const TIPOLOGIE = [
        'fondatore' => 'Fondatore',
        'volontario' => 'Volontario',
        'ordinario' => 'Ordinario',
    ];

    public const STATI = [
        'attivo' => 'Attivo',
        'recesso' => 'Recesso',
        'escluso' => 'Escluso',
        'sospeso' => 'Sospeso',
    ];

    protected function casts(): array
    {
        return [
            'data_nascita' => 'date',
            'data_ammissione' => 'date',
            'data_uscita' => 'date',
            'quota_sociale' => 'decimal:2',
            'capitale_versato' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Socio $socio): void {
            $socio->codice_fiscale = strtoupper((string) $socio->codice_fiscale);

            if (blank($socio->codice_socio)) {
                $nextId = ((int) static::withTrashed()->max('id')) + 1;
                $socio->codice_socio = sprintf('SOC-%04d', $nextId);
            }

            app(CodiceFiscaleParser::class)->applyToSocio($socio);
        });

        static::updating(function (Socio $socio): void {
            $socio->codice_fiscale = strtoupper((string) $socio->codice_fiscale);
            app(CodiceFiscaleParser::class)->applyToSocio($socio);
        });

        static::updated(function (Socio $socio): void {
            foreach ($socio->getChanges() as $field => $newValue) {
                if (in_array($field, ['updated_at'], true)) {
                    continue;
                }

                $socio->changes()->create([
                    'user_id' => auth()->id(),
                    'field' => $field,
                    'old_value' => $socio->getOriginal($field),
                    'new_value' => $newValue,
                ]);
            }
        });
    }

    public function comuneNascita(): BelongsTo
    {
        return $this->belongsTo(Comune::class, 'comune_nascita_id');
    }

    public function comuneResidenza(): BelongsTo
    {
        return $this->belongsTo(Comune::class, 'comune_residenza_id');
    }

    public function verbales(): HasMany
    {
        return $this->hasMany(Verbale::class);
    }

    public function workContracts(): HasMany
    {
        return $this->hasMany(SocioWorkContract::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(SocioVariation::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SocioChange::class);
    }

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where('stato', 'attivo');
    }

    public function getNomeCompletoAttribute(): string
    {
        return trim("{$this->nome} {$this->cognome}");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
