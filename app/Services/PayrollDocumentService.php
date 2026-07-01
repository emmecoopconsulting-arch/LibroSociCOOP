<?php

namespace App\Services;

use App\Models\PayrollDistribution;
use App\Models\SocioDocument;
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
}
