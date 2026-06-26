<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\Verbale;
use App\Models\WorkOrder;
use App\Models\WorkReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3ArchiveService
{
    public function archiveWorkOrderPdf(WorkOrder $order, string $localPath, ?string $contents = null): bool
    {
        $date = $order->data_servizio ?? now();

        return $this->archive(
            $localPath,
            sprintf('ODS/%s/%s/%s', $date->format('Y'), $date->format('m'), basename($localPath)),
            $contents,
        );
    }

    public function archiveSocioLocalFile(Socio $socio, string $section, string $localPath, ?string $contents = null): bool
    {
        return $this->archive(
            $localPath,
            sprintf(
                'SOCI/%s/%s/%s/%s',
                $socio->stato === 'attivo' ? 'Attivi' : 'Archiviati',
                $this->socioFolder($socio),
                $this->folder($section),
                basename($localPath),
            ),
            $contents,
        );
    }

    public function archiveWorkReportAttachment(WorkReport $report): bool
    {
        $date = $report->data_intervento ?? now();

        return $this->archive(
            $report->rapportino_path,
            sprintf(
                'RAPPORTI_INTERVENTI/%s/%s/%s/%s',
                $date->format('Y'),
                $date->format('m'),
                $this->folder($report->protocollo ?: 'senza-protocollo'),
                basename($report->rapportino_path),
            ),
        );
    }

    public function syncExistingArchive(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $count = 0;

        Socio::query()
            ->with(['documents', 'verbales'])
            ->chunkById(100, function ($soci) use (&$count): void {
                foreach ($soci as $socio) {
                    if (filled($socio->verbale_cda_path) && $this->archiveSocioLocalFile($socio, 'verbali-cda', $socio->verbale_cda_path)) {
                        $count++;
                    }

                    foreach ($socio->documents as $document) {
                        if (filled($document->file_path) && $this->archiveSocioLocalFile($socio, 'documenti', $document->file_path)) {
                            $count++;
                        }
                    }

                    foreach ($socio->verbales as $verbale) {
                        if (filled($verbale->file_path) && $this->archiveVerbale($verbale)) {
                            $count++;
                        }
                    }
                }
            });

        WorkOrder::query()
            ->whereNotNull('pdf_path')
            ->chunkById(100, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    if ($this->archiveWorkOrderPdf($order, $order->pdf_path)) {
                        $count++;
                    }
                }
            });

        WorkReport::query()
            ->whereNotNull('rapportino_path')
            ->chunkById(100, function ($reports) use (&$count): void {
                foreach ($reports as $report) {
                    if ($this->archiveWorkReportAttachment($report)) {
                        $count++;
                    }
                }
            });

        return $count;
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
}
