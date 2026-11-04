<?php

namespace App\Models;

use App\Services\CodiceFiscaleParser;
use App\Services\S3ArchiveService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'socio_svantaggiato',
    'svantaggio_categoria',
    'svantaggio_data_decorrenza',
    'svantaggio_data_cessazione',
    'svantaggio_ente',
    'svantaggio_riferimento',
    'svantaggio_data_documento',
    'svantaggio_documento_path',
    'svantaggio_note',
    'is_cda_member',
    'carica_sociale',
    'data_uscita',
    'ha_permesso_soggiorno',
    'scadenza_permesso_soggiorno',
    'mansione',
    'quota_sociale',
    'capitale_versato',
    'note',
])]
class Socio extends Model
{
    use LogsActivity, SoftDeletes;

    public const TIPOLOGIA_MEMBRO_CDA = 'membro_cda';

    public const TIPOLOGIE = [
        'fondatore' => 'Fondatore',
        'volontario' => 'Volontario',
        'ordinario' => 'Ordinario',
        self::TIPOLOGIA_MEMBRO_CDA => 'Membro CDA (non socio)',
    ];

    public const STATI = [
        'attivo' => 'Attivo',
        'recesso' => 'Recesso',
        'escluso' => 'Escluso',
        'sospeso' => 'Sospeso',
    ];

    public const CARICHE_SOCIALI = [
        'presidente' => 'Presidente',
        'consigliere' => 'Consigliere',
    ];

    public const CATEGORIE_SVANTAGGIO = [
        'invalidita_fisica' => 'Invalidità fisica',
        'invalidita_psichica' => 'Invalidità psichica',
        'invalidita_sensoriale' => 'Invalidità sensoriale',
        'ex_degente_psichiatrico' => 'Ex degente di ospedale psichiatrico, anche giudiziario',
        'trattamento_psichiatrico' => 'Persona in trattamento psichiatrico',
        'dipendenza_sostanze' => 'Persona con dipendenza da sostanze',
        'dipendenza_alcol' => 'Persona con dipendenza da alcol',
        'minore_difficolta_familiare' => 'Minore in età lavorativa in situazione di difficoltà familiare',
        'detenuto_internato' => 'Persona detenuta o internata',
        'misura_alternativa' => 'Condannato o internato ammesso a misura alternativa o al lavoro all’esterno',
        'altra_categoria_normativa' => 'Altra categoria prevista dalla normativa',
    ];

    protected function casts(): array
    {
        return [
            'data_nascita' => 'date',
            'data_ammissione' => 'date',
            'data_uscita' => 'date',
            'is_cda_member' => 'boolean',
            'socio_svantaggiato' => 'boolean',
            'svantaggio_data_decorrenza' => 'date',
            'svantaggio_data_cessazione' => 'date',
            'svantaggio_data_documento' => 'date',
            'ha_permesso_soggiorno' => 'boolean',
            'scadenza_permesso_soggiorno' => 'date',
            'quota_sociale' => 'decimal:2',
            'capitale_versato' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Socio $socio): void {
            $socio->codice_fiscale = strtoupper((string) $socio->codice_fiscale);
            $socio->normalizeDerivedFields();

            if (blank($socio->codice_socio)) {
                $nextId = ((int) static::withTrashed()->max('id')) + 1;
                $socio->codice_socio = sprintf('SOC-%04d', $nextId);
            }

            app(CodiceFiscaleParser::class)->applyToSocio($socio);
        });

        static::created(function (Socio $socio): void {
            if (filled($socio->verbale_cda_path)) {
                app(S3ArchiveService::class)->archiveSocioLocalFile($socio, 'verbali-cda', $socio->verbale_cda_path);
            }

            if (filled($socio->svantaggio_documento_path)) {
                app(S3ArchiveService::class)->archiveSocioLocalFile($socio, 'svantaggio-soci', $socio->svantaggio_documento_path);
            }
        });

        static::updating(function (Socio $socio): void {
            $socio->codice_fiscale = strtoupper((string) $socio->codice_fiscale);
            $socio->normalizeDerivedFields();
            app(CodiceFiscaleParser::class)->applyToSocio($socio);
        });

        static::updated(function (Socio $socio): void {
            foreach ($socio->getChanges() as $field => $newValue) {
                if (in_array($field, [
                    'updated_at',
                    'socio_svantaggiato',
                    'svantaggio_categoria',
                    'svantaggio_data_decorrenza',
                    'svantaggio_data_cessazione',
                    'svantaggio_ente',
                    'svantaggio_riferimento',
                    'svantaggio_data_documento',
                    'svantaggio_documento_path',
                    'svantaggio_note',
                ], true)) {
                    continue;
                }

                $socio->changes()->create([
                    'user_id' => auth()->id(),
                    'field' => $field,
                    'old_value' => $socio->getOriginal($field),
                    'new_value' => $newValue,
                ]);
            }

            if ($socio->wasChanged('verbale_cda_path') && filled($socio->verbale_cda_path)) {
                app(S3ArchiveService::class)->archiveSocioLocalFile($socio, 'verbali-cda', $socio->verbale_cda_path);
            }

            if ($socio->wasChanged('svantaggio_documento_path') && filled($socio->svantaggio_documento_path)) {
                app(S3ArchiveService::class)->archiveSocioLocalFile($socio, 'svantaggio-soci', $socio->svantaggio_documento_path);
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

    public function medicalVisits(): HasMany
    {
        return $this->hasMany(SocioMedicalVisit::class);
    }

    public function latestMedicalVisit(): HasOne
    {
        return $this->hasOne(SocioMedicalVisit::class)->ofMany(['data_visita' => 'max', 'id' => 'max']);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SocioDocument::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(SocioVariation::class);
    }

    public function workSiteAssignments(): HasMany
    {
        return $this->hasMany(WorkSiteAssignment::class);
    }

    public function workAbsences(): HasMany
    {
        return $this->hasMany(WorkAbsence::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SocioChange::class);
    }

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where('stato', 'attivo');
    }

    public function scopeSociEffettivi(Builder $query): Builder
    {
        return $query->where('tipologia', '!=', self::TIPOLOGIA_MEMBRO_CDA);
    }

    public function scopeConCapitaleSociale(Builder $query): Builder
    {
        return $query->sociEffettivi()->where('stato', '!=', 'recesso');
    }

    public function scopeOrdinari(Builder $query): Builder
    {
        return $query->where('tipologia', 'ordinario');
    }

    public function getNomeCompletoAttribute(): string
    {
        return trim("{$this->nome} {$this->cognome}");
    }

    private function normalizeDerivedFields(): void
    {
        if ($this->stato === 'recesso') {
            $this->quota_sociale = 0;
            $this->capitale_versato = 0;
        }

        if ($this->tipologia === self::TIPOLOGIA_MEMBRO_CDA) {
            $this->is_cda_member = true;
            $this->quota_sociale = 0;
            $this->capitale_versato = 0;
        }

        if ($this->carica_sociale) {
            $this->is_cda_member = true;
        }

        if (! $this->ha_permesso_soggiorno) {
            $this->scadenza_permesso_soggiorno = null;
        }

        if ($this->tipologia !== 'ordinario') {
            $this->mansione = null;
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept([
                'socio_svantaggiato',
                'svantaggio_categoria',
                'svantaggio_data_decorrenza',
                'svantaggio_data_cessazione',
                'svantaggio_ente',
                'svantaggio_riferimento',
                'svantaggio_data_documento',
                'svantaggio_documento_path',
                'svantaggio_note',
            ]);
    }
}
