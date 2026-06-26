<?php

namespace App\Services;

use App\Models\DocumentHeaderSetting;
use App\Models\Socio;
use App\Models\SocioVariation;
use App\Models\Verbale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VerbalePdfService
{
    public function __construct(
        private readonly PdfPageNumberService $pageNumberService,
        private readonly VerbaleTemplateService $templateService,
        private readonly S3ArchiveService $s3ArchiveService,
    ) {}

    public function ensureAdmissionVerbale(Socio $socio): Verbale
    {
        return Verbale::query()->firstOrCreate(
            ['socio_id' => $socio->id, 'tipo' => 'ammissione'],
            [
                'stato' => 'da_generare',
                'titolo' => $this->titolo($socio),
                'data_verbale' => $socio->data_ammissione,
            ],
        );
    }

    public function generateAdmission(Socio $socio): Verbale
    {
        $verbale = $this->ensureAdmissionVerbale($socio);
        $verbale->forceFill([
            'titolo' => $this->titolo($socio),
            'data_verbale' => $socio->data_ammissione,
        ])->save();

        $pdf = $this->pageNumberService->apply(Pdf::loadView('pdf.verbali.template', [
            'content' => $this->templateService->render($verbale, $this->riepilogoSociale($verbale, $socio)),
            'documentHeader' => DocumentHeaderSetting::current(),
            'socio' => $socio,
            'verbale' => $verbale,
        ]));

        $path = sprintf('verbali/%s/verbale-%s.pdf', now()->format('Y'), str($socio->codice_socio)->slug());
        $contents = $pdf->output();

        Storage::disk('local')->put($path, $contents);
        $this->s3ArchiveService->archiveSocioLocalFile($socio, 'verbali', $path, $contents);

        $verbale->update([
            'stato' => 'generato',
            'file_path' => $path,
            'generato_il' => now(),
        ]);

        return $verbale;
    }

    public function generate(Verbale $verbale): Verbale
    {
        if ($verbale->tipo === 'ammissione') {
            return $this->generateAdmission($verbale->socio);
        }

        return $this->generateVariation($verbale);
    }

    public function downloadResponse(Verbale $verbale, bool $regenerate = false): BinaryFileResponse
    {
        if ($regenerate || blank($verbale->file_path) || ! Storage::disk('local')->exists($verbale->file_path)) {
            $verbale = $this->generate($verbale);
        }

        return response()->download(
            Storage::disk('local')->path($verbale->file_path),
            $this->downloadFilename($verbale),
        );
    }

    public function generateVariation(Verbale $verbale): Verbale
    {
        $verbale->loadMissing(['socio', 'variation']);

        $pdf = $this->pageNumberService->apply(Pdf::loadView('pdf.verbali.template', [
            'content' => $this->templateService->render($verbale),
            'documentHeader' => DocumentHeaderSetting::current(),
            'socio' => $verbale->socio,
            'variation' => $verbale->variation,
            'verbale' => $verbale,
        ]));

        $path = sprintf(
            'verbali/%s/verbale-%s-%s-%s.pdf',
            now()->format('Y'),
            str($verbale->socio->codice_socio)->slug(),
            str(SocioVariation::TIPI[$verbale->tipo] ?? $verbale->tipo)->slug(),
            $verbale->id,
        );

        $contents = $pdf->output();

        Storage::disk('local')->put($path, $contents);
        $this->s3ArchiveService->archiveSocioLocalFile($verbale->socio, 'verbali', $path, $contents);

        $verbale->update([
            'stato' => 'generato',
            'file_path' => $path,
            'generato_il' => now(),
        ]);

        return $verbale;
    }

    public function generateMissingAdmissions(): int
    {
        $count = 0;

        Socio::query()
            ->sociEffettivi()
            ->where('stato', 'attivo')
            ->whereDoesntHave('verbales', fn ($query) => $query
                ->where('tipo', 'ammissione')
                ->where('stato', 'generato'))
            ->chunkById(100, function ($soci) use (&$count): void {
                foreach ($soci as $socio) {
                    $this->generateAdmission($socio);
                    $count++;
                }
            });

        return $count;
    }

    public function generateAllPending(): int
    {
        $count = 0;

        Verbale::query()
            ->where('stato', 'da_generare')
            ->with('socio')
            ->chunkById(100, function ($verbales) use (&$count): void {
                foreach ($verbales as $verbale) {
                    $this->generate($verbale);
                    $count++;
                }
            });

        return $count + $this->generateMissingAdmissions();
    }

    public function riepilogoSociale(Verbale $verbale, Socio $socio): array
    {
        $isAmmissione = $verbale->tipo === 'ammissione';
        $isUscita = in_array($verbale->tipo, ['recesso', 'esclusione'], true);
        $isOrdinario = $socio->tipologia === 'ordinario';
        $capitaleSocio = (float) $socio->capitale_versato;

        $sociOrdinariAltri = Socio::query()
            ->whereKeyNot($socio->getKey())
            ->ordinari()
            ->count();

        $capitaleAltri = (float) Socio::query()
            ->whereKeyNot($socio->getKey())
            ->sociEffettivi()
            ->sum('capitale_versato');

        $sociOrdinariEntrati = $isAmmissione && $isOrdinario ? 1 : 0;
        $sociOrdinariUsciti = $isUscita && $isOrdinario ? 1 : 0;
        $capitaleEntrato = $isAmmissione ? $capitaleSocio : 0.0;
        $capitaleUscito = $isUscita ? $capitaleSocio : 0.0;

        $sociOrdinariPrima = $sociOrdinariAltri + $sociOrdinariUsciti;
        $capitalePrima = $capitaleAltri + $capitaleUscito;

        return [
            'soci_ordinari_prima' => $sociOrdinariPrima,
            'capitale_sociale_prima' => $capitalePrima,
            'soci_ordinari_entrati' => $sociOrdinariEntrati,
            'soci_ordinari_usciti' => $sociOrdinariUsciti,
            'soci_ordinari_complessivi' => $sociOrdinariPrima + $sociOrdinariEntrati - $sociOrdinariUsciti,
            'capitale_sociale_entrato' => $capitaleEntrato,
            'capitale_sociale_uscito' => $capitaleUscito,
            'capitale_sociale_complessivo' => $capitalePrima + $capitaleEntrato - $capitaleUscito,
        ];
    }

    private function titolo(Socio $socio): string
    {
        $year = $socio->data_ammissione?->format('Y') ?? now()->format('Y');

        return "Verbale {$socio->codice_socio}/{$year}";
    }

    private function downloadFilename(Verbale $verbale): string
    {
        $tipo = str(SocioVariation::TIPI[$verbale->tipo] ?? Verbale::TIPI[$verbale->tipo] ?? $verbale->tipo)->slug();
        $codiceSocio = str($verbale->socio?->codice_socio ?? 'socio')->slug();

        return "verbale-{$codiceSocio}-{$tipo}-{$verbale->id}.pdf";
    }
}
