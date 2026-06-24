<?php

namespace App\Services;

use App\Models\Assemblea;
use App\Models\DocumentHeaderSetting;
use App\Models\Socio;
use App\Models\SocioVariation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssembleaService
{
    public function __construct(
        private readonly PdfPageNumberService $pageNumberService,
        private readonly SocioVariationService $variationService,
        private readonly VerbalePdfService $verbalePdfService,
    ) {}

    public function createWithVariations(array $data): Assemblea
    {
        $assemblea = DB::transaction(function () use ($data): Assemblea {
            $assemblea = Assemblea::create([
                'data_assemblea' => $data['data_assemblea'],
                'titolo' => $data['titolo'] ?: 'Assemblea del ' . $data['data_assemblea'],
                'note' => $data['note'] ?? null,
                'presidente' => $data['presidente'] ?? null,
                'segretario' => $data['segretario'] ?? null,
                'luogo' => $data['luogo'] ?? null,
                'modalita' => $data['modalita'] ?? 'presenza',
                'stato' => 'generata',
                'started_at' => now(),
                'closed_at' => now(),
            ]);

            foreach ($data['variations'] ?? [] as $variationData) {
                $variation = $this->variationService->createAndApply([
                    ...Arr::only($variationData, [
                        'socio_id',
                        'tipo',
                        'data_effetto',
                        'tipo_contratto',
                        'data_inizio',
                        'data_fine',
                        'ore_settimanali',
                        'note',
                    ]),
                    'data_verbale' => $data['data_assemblea'],
                ]);

                $variation->update(['assemblea_id' => $assemblea->id]);
            }

            return $assemblea;
        });

        $assemblea->loadMissing(['variations.socio', 'variations.verbale']);

        foreach ($assemblea->variations as $variation) {
            if ($variation->verbale) {
                $this->verbalePdfService->generate($variation->verbale);
            }
        }

        return $this->generatePdf($assemblea->refresh());
    }

    public function startDigital(array $data): Assemblea
    {
        return DB::transaction(function () use ($data): Assemblea {
            $assemblea = Assemblea::create([
                'data_assemblea' => $data['data_assemblea'],
                'titolo' => $data['titolo'] ?: 'Assemblea del ' . $data['data_assemblea'],
                'note' => $data['note'] ?? null,
                'presidente' => $data['presidente'] ?? null,
                'segretario' => $data['segretario'] ?? null,
                'luogo' => $data['luogo'] ?? null,
                'modalita' => $data['modalita'] ?? 'presenza',
                'stato' => 'in_corso',
                'started_at' => now(),
            ]);

            $this->syncPresenze($assemblea, $this->presenzeFromDigitalData($data));
            $this->syncPuntiOdg($assemblea, $data['punti_odg'] ?? []);

            return $assemblea->refresh();
        });
    }

    public function updateDigital(Assemblea $assemblea, array $data): Assemblea
    {
        return DB::transaction(function () use ($assemblea, $data): Assemblea {
            $assemblea->update([
                'data_assemblea' => $data['data_assemblea'],
                'titolo' => $data['titolo'] ?: 'Assemblea del ' . $data['data_assemblea'],
                'note' => $data['note'] ?? null,
                'presidente' => $data['presidente'] ?? null,
                'segretario' => $data['segretario'] ?? null,
                'luogo' => $data['luogo'] ?? null,
                'modalita' => $data['modalita'] ?? 'presenza',
            ]);

            $this->syncPresenze($assemblea, $this->presenzeFromDigitalData($data));
            $this->syncPuntiOdg($assemblea, $data['punti_odg'] ?? []);

            return $assemblea->refresh();
        });
    }

    public function closeDigital(Assemblea $assemblea, array $data): Assemblea
    {
        if ($assemblea->stato === 'generata') {
            return $assemblea;
        }

        if ($assemblea->stato === 'chiusa') {
            $assemblea = $this->generatePdf($assemblea->refresh());
            $assemblea->update(['stato' => 'generata']);

            return $assemblea->refresh();
        }

        $assemblea = DB::transaction(function () use ($assemblea, $data): Assemblea {
            $assemblea = $this->updateDigital($assemblea, $data);

            foreach ($data['variations'] ?? [] as $variationData) {
                if (blank($variationData['socio_id'] ?? null) || blank($variationData['tipo'] ?? null)) {
                    continue;
                }

                $variation = $this->variationService->createAndApply([
                    ...Arr::only($variationData, [
                        'socio_id',
                        'tipo',
                        'data_effetto',
                        'tipo_contratto',
                        'data_inizio',
                        'data_fine',
                        'ore_settimanali',
                        'note',
                    ]),
                    'data_verbale' => $data['data_assemblea'],
                ]);

                $variation->update(['assemblea_id' => $assemblea->id]);
            }

            $assemblea->update([
                'stato' => 'chiusa',
                'closed_at' => now(),
            ]);

            return $assemblea->refresh();
        });

        $assemblea->loadMissing(['variations.socio', 'variations.verbale']);

        foreach ($assemblea->variations as $variation) {
            if ($variation->verbale) {
                $this->verbalePdfService->generate($variation->verbale);
            }
        }

        $assemblea = $this->generatePdf($assemblea->refresh());
        $assemblea->update(['stato' => 'generata']);

        return $assemblea->refresh();
    }

    public function generatePdf(Assemblea $assemblea): Assemblea
    {
        $assemblea->loadMissing(['variations.socio', 'variations.verbale', 'presenze.socio', 'puntiOdg']);

        $pdf = $this->pageNumberService->apply(Pdf::loadView('pdf.assemblea', [
            'assemblea' => $assemblea,
            'documentHeader' => DocumentHeaderSetting::current(),
            'tipoLabels' => SocioVariation::TIPI,
        ]));

        $path = sprintf(
            'assemblee/%s/verbale-assemblea-%s-%s.pdf',
            $assemblea->data_assemblea->format('Y'),
            $assemblea->data_assemblea->format('Ymd'),
            $assemblea->id,
        );

        Storage::disk('local')->put($path, $pdf->output());

        $assemblea->update([
            'file_path' => $path,
            'generato_il' => now(),
        ]);

        return $assemblea;
    }

    public function downloadResponse(Assemblea $assemblea, bool $regenerate = false): BinaryFileResponse
    {
        if ($regenerate || blank($assemblea->file_path) || ! Storage::disk('local')->exists($assemblea->file_path)) {
            $assemblea = $this->generatePdf($assemblea);
        }

        return response()->download(
            Storage::disk('local')->path($assemblea->file_path),
            "verbale-assemblea-{$assemblea->data_assemblea->format('Ymd')}-{$assemblea->id}.pdf",
        );
    }

    public function defaultPresenze(): array
    {
        return Socio::query()
            ->sociEffettivi()
            ->attivi()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->map(fn (Socio $socio): array => [
                'socio_id' => $socio->id,
                'socio_label' => "{$socio->codice_socio} - {$socio->cognome} {$socio->nome}",
                'stato' => 'assente',
                'note' => null,
            ])
            ->all();
    }

    public function presenzeFromDigitalData(array $data): array
    {
        $presentIds = collect($data['present_socio_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $deleghe = collect($data['deleghe'] ?? [])
            ->filter(fn (array $delega): bool => filled($delega['socio_id'] ?? null))
            ->mapWithKeys(fn (array $delega): array => [
                (int) $delega['socio_id'] => trim((string) ($delega['delegato_nome'] ?? '')),
            ]);

        return Socio::query()
            ->sociEffettivi()
            ->attivi()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get()
            ->map(function (Socio $socio) use ($presentIds, $deleghe): array {
                $stato = 'assente';
                $note = null;

                if ($deleghe->has($socio->id)) {
                    $stato = 'delega';
                    $delegato = $deleghe->get($socio->id);
                    $note = $delegato ? "Delega a {$delegato}" : null;
                } elseif ($presentIds->contains($socio->id)) {
                    $stato = 'presente';
                }

                return [
                    'socio_id' => $socio->id,
                    'socio_label' => "{$socio->codice_socio} - {$socio->cognome} {$socio->nome}",
                    'stato' => $stato,
                    'note' => $note,
                ];
            })
            ->all();
    }

    private function syncPresenze(Assemblea $assemblea, array $presenze): void
    {
        foreach ($presenze as $presenzaData) {
            if (blank($presenzaData['socio_id'] ?? null)) {
                continue;
            }

            $stato = $presenzaData['stato'] ?? 'assente';
            $existing = $assemblea->presenze()
                ->where('socio_id', $presenzaData['socio_id'])
                ->first();

            $presenteAt = in_array($stato, ['presente', 'delega'], true)
                ? ($existing?->presente_at ?? now())
                : null;

            $assemblea->presenze()->updateOrCreate(
                ['socio_id' => $presenzaData['socio_id']],
                [
                    'stato' => $stato,
                    'presente_at' => $presenteAt,
                    'note' => $presenzaData['note'] ?? null,
                ],
            );
        }
    }

    private function syncPuntiOdg(Assemblea $assemblea, array $puntiOdg): void
    {
        $assemblea->puntiOdg()->delete();

        foreach (array_values($puntiOdg) as $index => $puntoData) {
            if (blank($puntoData['titolo'] ?? null)) {
                continue;
            }

            $assemblea->puntiOdg()->create([
                'ordine' => $index + 1,
                'titolo' => $puntoData['titolo'],
                'descrizione' => $puntoData['descrizione'] ?? null,
                'discussione' => $puntoData['discussione'] ?? null,
                'esito' => $puntoData['esito'] ?? 'da_discutere',
                'voti_favorevoli' => $puntoData['voti_favorevoli'] ?? null,
                'voti_contrari' => $puntoData['voti_contrari'] ?? null,
                'astenuti' => $puntoData['astenuti'] ?? null,
            ]);
        }
    }
}
