<?php

namespace App\Services;

use App\Models\PayrollDistribution;
use App\Models\SocioDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayrollDocumentService
{
    public function __construct(
        private readonly LocalPayrollOcrService $ocrService,
        private readonly S3ArchiveService $archiveService,
    ) {}

    /**
     * Crea un unico documento storico per ogni socio assegnato alla distribuzione.
     *
     * @return array<int, SocioDocument> indicizzato per socio_id
     */
    public function sync(PayrollDistribution $distribution): array
    {
        $distribution->load('pages');
        $source = Storage::disk('local')->path($distribution->source_path);
        $assignedGroups = $distribution->pages
            ->whereNotNull('socio_id')
            ->groupBy('socio_id');
        $documents = [];

        foreach ($assignedGroups as $socioId => $pages) {
            $relativePath = "documenti-soci/buste-paga/{$socioId}/distribuzione-{$distribution->id}.pdf";
            Storage::disk('local')->makeDirectory(dirname($relativePath));
            $this->ocrService->extractPages(
                $source,
                $pages->sortBy('page_number')->pluck('page_number')->all(),
                Storage::disk('local')->path($relativePath),
            );

            $documents[(int) $socioId] = SocioDocument::query()->updateOrCreate(
                [
                    'payroll_distribution_id' => $distribution->id,
                    'socio_id' => (int) $socioId,
                ],
                [
                    'tipo' => 'busta_paga',
                    'periodo_riferimento' => $distribution->period,
                    'numero_documento' => "BUSTA-{$distribution->id}-{$socioId}",
                    'file_path' => $relativePath,
                    'note' => "Generata dalla distribuzione “Buste {$distribution->period}”.",
                ],
            );
            $documents[(int) $socioId]->loadMissing('socio');
            $this->archiveService->archiveSocioLocalFile(
                $documents[(int) $socioId]->socio,
                'documenti',
                $relativePath,
            );
        }

        $staleDocuments = SocioDocument::query()
            ->where('payroll_distribution_id', $distribution->id)
            ->when(
                $assignedGroups->isNotEmpty(),
                fn ($query) => $query->whereNotIn('socio_id', $assignedGroups->keys()),
            )
            ->get();

        foreach ($staleDocuments as $document) {
            Storage::disk('local')->delete($document->file_path);
            $document->delete();
        }

        return $documents;
    }

    public function combinedWithoutEmail(PayrollDistribution $distribution): ?string
    {
        $distribution->load('pages.socio');
        $pagesWithoutEmail = $distribution->pages
            ->filter(fn ($page): bool => $page->socio_id !== null && blank($page->socio?->email))
            ->sortBy('page_number');
        $pageNumbers = $pagesWithoutEmail->pluck('page_number')->all();

        if ($pageNumbers === []) {
            return null;
        }

        $directory = "payroll/{$distribution->id}/exports";
        $relativePath = "{$directory}/buste-senza-email.pdf";
        $coverPath = "{$directory}/elenco-consegne-manuali.pdf";
        $payrollsPath = "{$directory}/sole-buste-senza-email.pdf";
        Storage::disk('local')->makeDirectory($directory);
        $this->ocrService->extractPages(
            Storage::disk('local')->path($distribution->source_path),
            $pageNumbers,
            Storage::disk('local')->path($payrollsPath),
        );
        $recipients = $pagesWithoutEmail
            ->groupBy('socio_id')
            ->map(fn ($pages) => [
                'socio' => $pages->first()->socio,
                'pages' => $pages->count(),
            ])
            ->values();
        Storage::disk('local')->put($coverPath, Pdf::loadView('pdf.payroll-manual-delivery-list', [
            'distribution' => $distribution,
            'recipients' => $recipients,
        ])->output());
        $this->ocrService->mergePdfFiles([
            Storage::disk('local')->path($coverPath),
            Storage::disk('local')->path($payrollsPath),
        ], Storage::disk('local')->path($relativePath));

        return $relativePath;
    }
}
