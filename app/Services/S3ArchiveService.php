<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Assemblea;
use App\Models\PayrollDistribution;
use App\Models\Socio;
use App\Models\SocioDocument;
use App\Models\Verbale;
use App\Models\WorkOrder;
use App\Models\WorkReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3ArchiveService
{
    public function archiveWorkOrderPdf(WorkOrder $order, string $localPath, ?string $contents = null): bool
    {
        return $this->archive($localPath, $this->workOrderS3Path($order, $localPath), $contents);
    }

    public function archiveSocioLocalFile(Socio $socio, string $section, string $localPath, ?string $contents = null): bool
    {
        return $this->archive($localPath, $this->socioS3Path($socio, $section, $localPath), $contents);
    }

    public function archiveSocioDocument(SocioDocument $document): bool
    {
        $document->loadMissing('socio');

        if (! $document->socio || blank($document->file_path)) {
            return false;
        }

        $archived = $this->archive(
            $document->file_path,
            $this->socioDocumentS3Path($document),
        );

        if ($archived) {
            $this->removeLegacySocioDocumentObject($document);
        }

        return $archived;
    }

    public function archivePayrollSource(PayrollDistribution $distribution): bool
    {
        if (blank($distribution->source_path)) {
            return false;
        }

        return $this->archive($distribution->source_path, $this->payrollSourceS3Path($distribution));
    }

    public function archiveAssemblyPdf(Assemblea $assemblea): bool
    {
        if (blank($assemblea->file_path)) {
            return false;
        }

        return $this->archive($assemblea->file_path, $this->assemblyS3Path($assemblea));
    }

    public function archiveWorkReportAttachment(WorkReport $report): bool
    {
        if (blank($report->rapportino_path)) {
            return false;
        }

        return $this->archive($report->rapportino_path, $this->workReportS3Path($report));
    }

    /**
     * @return array{enabled: bool, ok: bool, path: ?string, message: ?string}
     */
    public function testConnection(): array
    {
        if (! $this->enabled()) {
            return [
                'enabled' => false,
                'ok' => false,
                'path' => null,
                'message' => 'Configurazione S3 incompleta.',
            ];
        }

        $path = 'DIAGNOSTICA/connessione-s3.txt';

        try {
            $this->disk()->put($path, 'Connessione S3 verificata il '.Carbon::now()->format('Y-m-d H:i:s'));

            return [
                'enabled' => true,
                'ok' => true,
                'path' => $path,
                'message' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Test connessione S3 non riuscito.', [
                's3_path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [
                'enabled' => true,
                'ok' => false,
                'path' => $path,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{enabled: bool, checked: int, missing: int, uploaded: int, already_present: int, local_missing: int, failed: int}
     */
    public function syncExistingArchive(): array
    {
        $result = $this->emptySyncResult();

        if (! $this->enabled()) {
            return $result;
        }

        $result['enabled'] = true;

        Socio::query()
            ->with(['documents', 'medicalVisits', 'verbales'])
            ->chunkById(100, function ($soci) use (&$result): void {
                foreach ($soci as $socio) {
                    if (filled($socio->verbale_cda_path)) {
                        $this->syncLocalToS3($socio->verbale_cda_path, $this->socioS3Path($socio, 'verbali-cda', $socio->verbale_cda_path), $result);
                    }

                    foreach ($socio->documents as $document) {
                        if (filled($document->file_path)) {
                            $this->syncLocalToS3($document->file_path, $this->socioDocumentS3Path($document), $result);
                            $this->removeLegacySocioDocumentObject($document);
                        }
                    }

                    foreach ($socio->medicalVisits as $visit) {
                        if (filled($visit->pdf_path)) {
                            $this->syncLocalToS3($visit->pdf_path, $this->socioS3Path($socio, 'visite-mediche', $visit->pdf_path), $result);
                        }
                    }

                    foreach ($socio->verbales as $verbale) {
                        if (filled($verbale->file_path)) {
                            $this->syncLocalToS3($verbale->file_path, $this->socioS3Path($socio, 'verbali', $verbale->file_path), $result);
                        }
                    }
                }
            });

        PayrollDistribution::query()
            ->whereNotNull('source_path')
            ->chunkById(100, function ($distributions) use (&$result): void {
                foreach ($distributions as $distribution) {
                    $this->syncLocalToS3($distribution->source_path, $this->payrollSourceS3Path($distribution), $result);
                }
            });

        Assemblea::query()
            ->whereNotNull('file_path')
            ->chunkById(100, function ($assemblies) use (&$result): void {
                foreach ($assemblies as $assemblea) {
                    $this->syncLocalToS3($assemblea->file_path, $this->assemblyS3Path($assemblea), $result);
                }
            });

        WorkOrder::query()
            ->whereNotNull('pdf_path')
            ->chunkById(100, function ($orders) use (&$result): void {
                foreach ($orders as $order) {
                    $this->syncLocalToS3($order->pdf_path, $this->workOrderS3Path($order, $order->pdf_path), $result);
                }
            });

        WorkReport::query()
            ->whereNotNull('rapportino_path')
            ->chunkById(100, function ($reports) use (&$result): void {
                foreach ($reports as $report) {
                    $this->syncLocalToS3($report->rapportino_path, $this->workReportS3Path($report), $result);
                }
            });

        return $result;
    }

    private function archiveVerbale(Verbale $verbale): bool
    {
        $verbale->loadMissing('socio');

        return $verbale->socio
            ? $this->archiveSocioLocalFile($verbale->socio, 'verbali', $verbale->file_path)
            : false;
    }

    public function enabled(): bool
    {
        return AppSetting::bool(AppSetting::S3_ARCHIVE_ENABLED)
            && filled(AppSetting::string(AppSetting::S3_ACCESS_KEY_ID))
            && filled(AppSetting::s3SecretAccessKey())
            && filled(AppSetting::string(AppSetting::S3_DEFAULT_REGION))
            && filled(AppSetting::string(AppSetting::S3_BUCKET));
    }

    private function archive(string $localPath, string $s3Path, ?string $contents = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            if ($contents === null) {
                if (! Storage::disk('local')->exists($localPath)) {
                    return false;
                }

                $contents = Storage::disk('local')->get($localPath);
            }

            $this->disk()->put($s3Path, $contents);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Archiviazione parallela S3 non riuscita.', [
                'local_path' => $localPath,
                's3_path' => $s3Path,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array{enabled: bool, checked: int, missing: int, uploaded: int, already_present: int, local_missing: int, failed: int}  $result
     */
    private function syncLocalToS3(string $localPath, string $s3Path, array &$result): void
    {
        $result['checked']++;

        try {
            if (! Storage::disk('local')->exists($localPath)) {
                $result['local_missing']++;

                return;
            }

            $disk = $this->disk();

            try {
                if ($disk->exists($s3Path)) {
                    $result['already_present']++;

                    return;
                }
            } catch (\Throwable $exception) {
                Log::warning('Controllo esistenza oggetto S3 non riuscito, tento comunque il caricamento.', [
                    'local_path' => $localPath,
                    's3_path' => $s3Path,
                    'message' => $exception->getMessage(),
                ]);
            }

            $result['missing']++;
            $disk->put($s3Path, Storage::disk('local')->get($localPath));
            $result['uploaded']++;
        } catch (\Throwable $exception) {
            $result['failed']++;

            Log::warning('Verifica/sincronizzazione S3 non riuscita.', [
                'local_path' => $localPath,
                's3_path' => $s3Path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{enabled: bool, checked: int, missing: int, uploaded: int, already_present: int, local_missing: int, failed: int}
     */
    private function emptySyncResult(): array
    {
        return [
            'enabled' => false,
            'checked' => 0,
            'missing' => 0,
            'uploaded' => 0,
            'already_present' => 0,
            'local_missing' => 0,
            'failed' => 0,
        ];
    }

    private function workOrderS3Path(WorkOrder $order, string $localPath): string
    {
        $date = $order->data_servizio ?? now();

        return sprintf('ODS/%s/%s/%s', $date->format('Y'), $date->format('m'), basename($localPath));
    }

    private function socioS3Path(Socio $socio, string $section, string $localPath): string
    {
        return sprintf(
            'SOCI/%s/%s/%s/%s',
            $socio->stato === 'attivo' ? 'Attivi' : 'Archiviati',
            $this->socioFolder($socio),
            $this->folder($section),
            basename($localPath),
        );
    }

    private function socioDocumentS3Path(SocioDocument $document): string
    {
        $document->loadMissing('socio');
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $type = $document->tipo === 'busta_paga'
            ? 'busta-paga'
            : (SocioDocument::TIPI[$document->tipo] ?? $document->tipo ?? 'documento');
        $period = filled($document->periodo_riferimento)
            ? '-'.Str::slug($document->periodo_riferimento)
            : '';
        $number = filled($document->numero_documento)
            ? '-'.Str::slug($document->numero_documento)
            : '';
        $filename = sprintf(
            '%04d-%s%s%s.%s',
            $document->id,
            Str::slug($type),
            $period,
            $number,
            strtolower($extension),
        );

        return sprintf(
            'SOCI/%s/%s/%s/%s',
            $document->socio->stato === 'attivo' ? 'Attivi' : 'Archiviati',
            $this->socioFolder($document->socio),
            $this->folder('documenti'),
            $filename,
        );
    }

    private function payrollSourceS3Path(PayrollDistribution $distribution): string
    {
        $filename = sprintf(
            '%04d-buste-paga-%s-%s',
            $distribution->id,
            Str::slug($distribution->period) ?: 'periodo-non-indicato',
            $this->safeFilename($distribution->original_name ?: basename($distribution->source_path)),
        );

        return "BUSTE_PAGA/SORGENTI/{$filename}";
    }

    private function removeLegacySocioDocumentObject(SocioDocument $document): void
    {
        $canonicalPath = $this->socioDocumentS3Path($document);
        $legacyPath = $this->socioS3Path($document->socio, 'documenti', $document->file_path);

        if ($legacyPath === $canonicalPath) {
            return;
        }

        try {
            $disk = $this->disk();

            if ($disk->exists($canonicalPath) && $disk->exists($legacyPath)) {
                $disk->delete($legacyPath);
            }
        } catch (\Throwable $exception) {
            Log::warning('Rimozione del vecchio nome oggetto S3 non riuscita.', [
                'legacy_s3_path' => $legacyPath,
                'canonical_s3_path' => $canonicalPath,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function assemblyS3Path(Assemblea $assemblea): string
    {
        $date = $assemblea->data_assemblea ?? now();

        return sprintf(
            'ASSEMBLEE/%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            basename($assemblea->file_path),
        );
    }

    private function workReportS3Path(WorkReport $report): string
    {
        $date = $report->data_intervento ?? now();

        return sprintf(
            'RAPPORTI_INTERVENTI/%s/%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            $this->folder($report->protocollo ?: 'senza-protocollo'),
            basename($report->rapportino_path),
        );
    }

    private function disk(): Filesystem
    {
        return Storage::build([
            'driver' => 's3',
            'key' => AppSetting::string(AppSetting::S3_ACCESS_KEY_ID),
            'secret' => AppSetting::s3SecretAccessKey(),
            'region' => AppSetting::string(AppSetting::S3_DEFAULT_REGION),
            'bucket' => AppSetting::string(AppSetting::S3_BUCKET),
            'endpoint' => AppSetting::string(AppSetting::S3_ENDPOINT),
            'use_path_style_endpoint' => AppSetting::bool(AppSetting::S3_USE_PATH_STYLE_ENDPOINT),
            'throw' => true,
        ]);
    }

    private function socioFolder(Socio $socio): string
    {
        return $this->folder("{$socio->cognome} {$socio->nome} {$socio->codice_socio}");
    }

    private function folder(string $value): string
    {
        return Str::of($value)->ascii()->slug('-')->toString();
    }

    private function safeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return (Str::slug($name) ?: 'documento').($extension ? '.'.strtolower($extension) : '');
    }
}
