<?php

namespace App\Services;

use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\Verbale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class VerbaleProgressionService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(string $asOf, int $members, float $capital): Collection
    {
        $baseline = CarbonImmutable::parse($asOf)->toDateString();
        $events = collect();

        Socio::withTrashed()
            ->sociEffettivi()
            ->whereDate('data_ammissione', '>', $baseline)
            ->with(['verbales' => fn ($query) => $query->where('tipo', 'ammissione')->orderByDesc('id')])
            ->get()
            ->each(function (Socio $socio) use ($events): void {
                if (! $socio->data_ammissione) {
                    return;
                }

                $verbale = $socio->verbales->first() ?? new Verbale([
                    'socio_id' => $socio->id,
                    'tipo' => 'ammissione',
                    'stato' => 'da_generare',
                    'titolo' => sprintf('Verbale %s/%s', $socio->codice_socio, $socio->data_ammissione->format('Y')),
                    'data_verbale' => $socio->data_ammissione,
                ]);

                $ordinary = $socio->tipologia === 'ordinario';
                $events->push([
                    'verbale' => $verbale,
                    'socio' => $socio,
                    'variation' => null,
                    'date' => CarbonImmutable::parse($socio->data_ammissione),
                    'type' => 'ammissione',
                    'count_in' => $ordinary ? 1 : 0,
                    'count_out' => 0,
                    'capital_in' => $ordinary ? $this->capitalForSocio($socio) : 0.0,
                    'capital_out' => 0.0,
                ]);
            });

        SocioVariation::query()
            ->where('stato', 'applicata')
            ->whereDate('data_effetto', '>', $baseline)
            ->with([
                'socio' => fn ($query) => $query->withTrashed(),
                'verbale',
            ])
            ->orderBy('data_effetto')
            ->orderBy('id')
            ->get()
            ->each(function (SocioVariation $variation) use ($events): void {
                if (! $variation->socio || ! $variation->data_effetto) {
                    return;
                }

                $verbale = $variation->verbale ?? new Verbale([
                    'socio_id' => $variation->socio_id,
                    'tipo' => $variation->tipo,
                    'stato' => 'da_generare',
                    'titolo' => sprintf('%s %s del %s', SocioVariation::TIPI[$variation->tipo] ?? 'Variazione socio', $variation->socio->codice_socio, ($variation->data_verbale ?? $variation->data_effetto)->format('d/m/Y')),
                    'data_verbale' => $variation->data_verbale ?? $variation->data_effetto,
                ]);

                $isExit = in_array($variation->tipo, ['recesso', 'esclusione', 'cessazione_rapporto'], true);
                $ordinary = data_get($variation->snapshot_precedente, 'socio.tipologia', $variation->socio->tipologia) === 'ordinario';

                $events->push([
                    'verbale' => $verbale,
                    'socio' => $variation->socio,
                    'variation' => $variation,
                    'date' => CarbonImmutable::parse($variation->data_effetto),
                    'type' => $variation->tipo,
                    'count_in' => 0,
                    'count_out' => $isExit && $ordinary ? 1 : 0,
                    'capital_in' => 0.0,
                    'capital_out' => $isExit && $ordinary ? $this->capitalForSocio($variation->socio, $variation) : 0.0,
                ]);
            });

        $events = $events
            ->unique(fn (array $event): string => ($event['verbale']->id ?? 'new-'.($event['variation']?->id ?? $event['socio']->id)).'-'.$event['type'])
            ->sort(fn (array $a, array $b): int => [$a['date']->timestamp, $a['verbale']->id ?? (100000000 + ($a['variation']?->id ?? $a['socio']->id))] <=> [$b['date']->timestamp, $b['verbale']->id ?? (100000000 + ($b['variation']?->id ?? $b['socio']->id))])
            ->values();

        $sequence = collect();

        foreach ($events as $event) {
            $beforeMembers = $members;
            $beforeCapital = $capital;
            $members += $event['count_in'] - $event['count_out'];
            $capital += $event['capital_in'] - $event['capital_out'];

            $event['summary'] = [
                'soci_ordinari_prima' => $beforeMembers,
                'capitale_sociale_prima' => $beforeCapital,
                'soci_ordinari_entrati' => $event['count_in'],
                'soci_ordinari_usciti' => $event['count_out'],
                'soci_ordinari_complessivi' => $members,
                'capitale_sociale_entrato' => $event['capital_in'],
                'capitale_sociale_uscito' => $event['capital_out'],
                'capitale_sociale_complessivo' => $capital,
            ];
            $event['members_after'] = $members;
            $event['capital_after'] = $capital;
            $sequence->push($event);
        }

        return $sequence;
    }

    private function capitalForSocio(Socio $socio, ?SocioVariation $variation = null): float
    {
        $snapshotCapital = data_get($variation?->snapshot_precedente, 'socio.capitale_versato');

        if ($snapshotCapital !== null) {
            return (float) $snapshotCapital;
        }

        $currentCapital = (float) $socio->capitale_versato;

        if ($currentCapital > 0) {
            return $currentCapital;
        }

        $previousCapital = $socio->changes()
            ->where('field', 'capitale_versato')
            ->whereIn('new_value', ['0', '0.00'])
            ->whereRaw('CAST(old_value AS DECIMAL(10,2)) > 0')
            ->orderBy('id')
            ->value('old_value');

        return (float) ($previousCapital ?? 0);
    }
}
