<?php

namespace App\Services;

use App\Models\Assemblea;
use App\Models\DocumentHeaderSetting;
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
                'stato' => 'generata',
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

    public function generatePdf(Assemblea $assemblea): Assemblea
    {
        $assemblea->loadMissing(['variations.socio', 'variations.verbale']);

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
}
