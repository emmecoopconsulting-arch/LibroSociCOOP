<?php

namespace App\Services;

use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\SocioWorkContract;
use App\Models\Verbale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SocioVariationService
{
    public function createAndApply(array $data): SocioVariation
    {
        $variation = DB::transaction(function () use ($data): SocioVariation {
            $socio = Socio::query()->lockForUpdate()->findOrFail($data['socio_id']);
            $this->validateBusinessRules($socio, $data);

            $verbale = $this->createVerbale($socio, $data);
            $soloDocumentoInterno = in_array($data['tipo'], SocioVariation::TIPI_DOCUMENTAZIONE_INTERNA, true);

            return SocioVariation::create([
                ...Arr::only($data, [
                    'socio_id',
                    'tipo',
                    'data_verbale',
                    'data_effetto',
                    'tipo_contratto',
                    'data_inizio',
                    'data_fine',
                    'ore_settimanali',
                    'note',
                ]),
                'verbale_id' => $verbale->id,
                'stato' => $soloDocumentoInterno ? 'documentata' : 'bozza',
                'approvazione_cda' => $soloDocumentoInterno ? 'storica' : 'in_attesa',
                'snapshot_precedente' => $this->snapshot($socio),
                'snapshot_successivo' => $soloDocumentoInterno ? $this->snapshot($socio) : null,
            ]);
        });

        $variation->loadMissing('verbale');
        if ($variation->verbale) {
            app(VerbalePdfService::class)->generate($variation->verbale);
        }

        return $variation->refresh();
    }

    public function decidePending(
        SocioVariation $variation,
        \App\Models\Assemblea $assemblea,
        string $decisione,
        ?string $dataDelibera = null,
    ): SocioVariation {
        if (! array_key_exists($decisione, SocioVariation::DECISIONI_CDA)) {
            throw ValidationException::withMessages([
                'decisioni_variazioni_cda' => 'Seleziona un esito valido per la variazione.',
            ]);
        }

        $variation = DB::transaction(function () use ($variation, $assemblea, $decisione, $dataDelibera): SocioVariation {
            $variation = SocioVariation::query()->lockForUpdate()->findOrFail($variation->id);

            if ($variation->approvazione_cda !== 'in_attesa') {
                throw ValidationException::withMessages([
                    'decisioni_variazioni_cda' => 'La variazione non è più in attesa di decisione.',
                ]);
            }

            $assemblea->cdaDecisions()->updateOrCreate(
                ['socio_variation_id' => $variation->id],
                [
                    'decisione' => $decisione,
                    'data_delibera' => $dataDelibera ?: $assemblea->data_assemblea,
                ],
            );

            $variation->forceFill([
                'cda_assemblea_id' => $assemblea->id,
                'decisione_cda' => $decisione,
                'data_delibera_cda' => $dataDelibera ?: $assemblea->data_assemblea,
            ])->save();

            if ($decisione === 'rinviata') {
                return $variation->refresh();
            }

            if ($decisione === 'respinta') {
                $variation->update(['approvazione_cda' => 'respinta']);

                return $variation->refresh();
            }

            $socio = Socio::query()->lockForUpdate()->findOrFail($variation->socio_id);
            $verbale = $variation->verbale ?: $this->createVerbale($socio, [
                'tipo' => $variation->tipo,
                'data_verbale' => $variation->data_verbale,
            ]);

            $this->applyVariation($socio, $variation, $verbale);

            $variation->forceFill([
                'stato' => 'applicata',
                'approvazione_cda' => 'approvata',
                'snapshot_successivo' => $this->snapshot($socio->refresh()),
            ])->save();

            return $variation->refresh();
        });

        if ($variation->verbale) {
            app(VerbalePdfService::class)->generate($variation->verbale);
        }

        return $variation;
    }

    private function validateBusinessRules(Socio $socio, array $data): void
    {
        if ($this->isWorkVariation($data['tipo']) && $socio->tipologia !== 'ordinario') {
            throw ValidationException::withMessages([
                'socio_id' => 'Le variazioni contrattuali sono disponibili solo per i soci ordinari.',
            ]);
        }

        if (($data['tipo_contratto'] ?? null) === 'determinato' && blank($data['data_fine'] ?? null)) {
            throw ValidationException::withMessages([
                'data_fine' => 'La data fine e obbligatoria per i contratti a tempo determinato.',
            ]);
        }
    }

    private function createVerbale(Socio $socio, array $data): Verbale
    {
        return Verbale::create([
            'socio_id' => $socio->id,
            'tipo' => $data['tipo'],
            'stato' => 'da_generare',
            'titolo' => $this->verbaleTitle($socio, $data),
            'data_verbale' => $data['data_verbale'],
        ]);
    }

    private function applyVariation(Socio $socio, SocioVariation $variation, Verbale $verbale): void
    {
        match ($variation->tipo) {
            'variazione_contratto' => $this->applyWorkContract($socio, $variation, $verbale),
            'proroga_contratto' => $this->applyContractExtension($socio, $variation, $verbale),
            'trasformazione_indeterminato' => $this->applyOpenEndedTransformation($socio, $variation, $verbale),
            'variazione_ore' => $this->applyHoursVariation($socio, $variation, $verbale),
            'cessazione_rapporto' => $this->applyWorkTermination($socio, $variation),
            'recesso' => $socio->update(['stato' => 'recesso', 'data_uscita' => $variation->data_effetto]),
            'esclusione' => $socio->update(['stato' => 'escluso', 'data_uscita' => $variation->data_effetto]),
            // A work suspension is not a suspension of membership. Its employment
            // effects are handled under the cooperative's approved work regulation.
            'sospensione' => null,
            default => null,
        };
    }

    private function applyWorkContract(Socio $socio, SocioVariation $variation, Verbale $verbale): void
    {
        $contract = $this->activeContract($socio) ?? new SocioWorkContract([
            'socio_id' => $socio->id,
            'stato' => 'attivo',
        ]);

        $contract->fill([
            'verbale_id' => $verbale->id,
            'tipo_contratto' => $variation->tipo_contratto,
            'data_inizio' => $variation->data_inizio ?: $variation->data_effetto,
            'data_fine' => $variation->tipo_contratto === 'determinato' ? $variation->data_fine : null,
            'ore_settimanali' => $variation->ore_settimanali,
            'note' => $variation->note,
        ])->save();
    }

    private function applyContractExtension(Socio $socio, SocioVariation $variation, Verbale $verbale): void
    {
        $contract = $this->activeContract($socio);

        if (! $contract) {
            $contract = new SocioWorkContract([
                'socio_id' => $socio->id,
                'tipo_contratto' => 'determinato',
                'data_inizio' => $variation->data_inizio ?: $variation->data_effetto,
                'stato' => 'attivo',
            ]);
        }

        $contract->fill([
            'verbale_id' => $verbale->id,
            'tipo_contratto' => 'determinato',
            'data_fine' => $variation->data_fine,
            'ore_settimanali' => $variation->ore_settimanali ?: $contract->ore_settimanali,
        ])->save();
    }

    private function applyOpenEndedTransformation(Socio $socio, SocioVariation $variation, Verbale $verbale): void
    {
        $contract = $this->activeContract($socio) ?? new SocioWorkContract([
            'socio_id' => $socio->id,
            'data_inizio' => $variation->data_inizio ?: $variation->data_effetto,
            'stato' => 'attivo',
        ]);

        $contract->fill([
            'verbale_id' => $verbale->id,
            'tipo_contratto' => 'indeterminato',
            'data_fine' => null,
            'ore_settimanali' => $variation->ore_settimanali ?: $contract->ore_settimanali,
        ])->save();
    }

    private function applyHoursVariation(Socio $socio, SocioVariation $variation, Verbale $verbale): void
    {
        $contract = $this->activeContract($socio);

        if (! $contract) {
            throw ValidationException::withMessages([
                'ore_settimanali' => 'Non esiste un contratto attivo su cui applicare la variazione ore.',
            ]);
        }

        $contract->update([
            'verbale_id' => $verbale->id,
            'ore_settimanali' => $variation->ore_settimanali,
        ]);
    }

    private function applyWorkTermination(Socio $socio, SocioVariation $variation): void
    {
        $contract = $this->activeContract($socio);

        if ($contract) {
            $contract->update([
                'stato' => 'cessato',
                'data_fine' => $variation->data_effetto,
            ]);
        }

        // Ending the work relationship does not end membership in the cooperative.
    }

    private function activeContract(Socio $socio): ?SocioWorkContract
    {
        return $socio->workContracts()
            ->where('stato', 'attivo')
            ->latest('data_inizio')
            ->latest('id')
            ->first();
    }

    private function isWorkVariation(string $tipo): bool
    {
        return in_array($tipo, [
            'variazione_contratto',
            'proroga_contratto',
            'trasformazione_indeterminato',
            'variazione_ore',
            'cessazione_rapporto',
        ], true);
    }

    private function snapshot(Socio $socio): array
    {
        $contract = $this->activeContract($socio);

        return [
            'socio' => Arr::only($socio->attributesToArray(), [
                'tipologia',
                'stato',
                'data_uscita',
                'quota_sociale',
                'capitale_versato',
            ]),
            'contratto' => $contract?->attributesToArray(),
        ];
    }

    private function verbaleTitle(Socio $socio, array $data): string
    {
        $label = SocioVariation::TIPI[$data['tipo']] ?? 'Variazione socio';
        $date = $data['data_verbale'];

        return "{$label} {$socio->codice_socio} del {$date}";
    }
}
