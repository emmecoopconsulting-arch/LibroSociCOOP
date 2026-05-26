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
        return DB::transaction(function () use ($data): SocioVariation {
            $socio = Socio::query()->findOrFail($data['socio_id']);
            $this->validateBusinessRules($socio, $data);

            $previousSnapshot = $this->snapshot($socio);
            $verbale = $this->createVerbale($socio, $data);

            $variation = SocioVariation::create([
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
                'stato' => 'applicata',
                'snapshot_precedente' => $previousSnapshot,
            ]);

            $this->applyVariation($socio, $variation, $verbale);

            $variation->forceFill([
                'snapshot_successivo' => $this->snapshot($socio->refresh()),
            ])->save();

            return $variation;
        });
    }

    private function validateBusinessRules(Socio $socio, array $data): void
    {
        if ($this->isWorkVariation($data['tipo']) && $socio->tipologia !== 'lavoratore') {
            throw ValidationException::withMessages([
                'socio_id' => 'Le variazioni contrattuali sono disponibili solo per i soci lavoratori.',
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
            'sospensione' => $socio->update(['stato' => 'sospeso']),
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

        $socio->update([
            'stato' => 'recesso',
            'data_uscita' => $variation->data_effetto,
        ]);
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
