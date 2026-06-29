<?php

namespace App\Services;

use App\Models\PayrollDistribution;
use App\Models\Socio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LocalPayrollOcrService
{
    /**
     * @return array{tesseract: bool, pdftoppm: bool, languages: array<int, string>, ready: bool}
     */
    public function diagnostics(): array
    {
        $tesseract = $this->findExecutable('tesseract');
        $pdftoppm = $this->findExecutable('pdftoppm');
        $languages = [];

        if ($tesseract) {
            $process = new Process([$tesseract, '--list-langs']);
            $process->run();
            $languages = array_values(array_filter(array_map('trim', array_slice(
                preg_split('/\R/', $process->getOutput()) ?: [],
                1,
            ))));
        }

        return [
            'tesseract' => (bool) $tesseract,
            'pdftoppm' => (bool) $pdftoppm,
            'languages' => $languages,
            'ready' => (bool) $tesseract && (bool) $pdftoppm,
        ];
    }

    public function analyze(PayrollDistribution $distribution): void
    {
        $source = Storage::disk('local')->path($distribution->source_path);
        $diagnostics = $this->diagnostics();

        if (! $diagnostics['ready']) {
            throw new RuntimeException('OCR locale non disponibile: installare Tesseract e Poppler (pdftoppm).');
        }

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($source);
        $distribution->update(['total_pages' => $pageCount, 'status' => 'processing', 'error' => null]);
        $directory = "payroll/{$distribution->id}/pages";
        Storage::disk('local')->makeDirectory($directory);

        $socios = Socio::query()
            ->whereNotNull('email')
            ->get(['id', 'nome', 'cognome', 'codice_fiscale', 'codice_socio']);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pagePath = "{$directory}/page-{$pageNumber}.pdf";
            $this->extractPages($source, [$pageNumber], Storage::disk('local')->path($pagePath));
            $text = $this->ocrPage($source, $pageNumber, $diagnostics['languages']);
            $match = $this->matchSocio($text, $socios);

            $distribution->pages()->updateOrCreate(
                ['page_number' => $pageNumber],
                [
                    'page_path' => $pagePath,
                    'socio_id' => $match['socio_id'],
                    'match_confidence' => $match['confidence'],
                    'match_reason' => $match['reason'],
                    'ocr_text' => $text,
                ],
            );
        }

        $distribution->update(['status' => 'review']);
    }

    /**
     * @param  array<int, int>  $pageNumbers
     */
    public function extractPages(string $source, array $pageNumbers, string $destination): void
    {
        $output = new Fpdi;
        $output->setSourceFile($source);

        foreach ($pageNumbers as $pageNumber) {
            $template = $output->importPage($pageNumber);
            $size = $output->getTemplateSize($template);
            $output->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $output->useTemplate($template);
        }

        $output->Output('F', $destination);
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function ocrPage(string $source, int $pageNumber, array $languages): string
    {
        $prefix = storage_path('app/private/payroll/ocr-'.Str::uuid());
        $pdftoppm = $this->findExecutable('pdftoppm');
        $tesseract = $this->findExecutable('tesseract');

        $render = new Process([
            $pdftoppm, '-f', (string) $pageNumber, '-l', (string) $pageNumber,
            '-singlefile', '-jpeg', '-r', '220', $source, $prefix,
        ]);
        $render->setTimeout(120);
        $render->mustRun();

        $image = "{$prefix}.jpg";
        $language = in_array('ita', $languages, true) ? 'ita+eng' : 'eng';
        $ocr = new Process([$tesseract, $image, 'stdout', '-l', $language, '--psm', '6']);
        $ocr->setTimeout(120);

        try {
            $ocr->mustRun();

            return trim($ocr->getOutput());
        } finally {
            @unlink($image);
        }
    }

    /**
     * @param  Collection<int, Socio>  $socios
     * @return array{socio_id: int|null, confidence: int, reason: string}
     */
    public function matchSocio(string $text, $socios): array
    {
        $normalized = $this->normalize($text);
        $compact = str_replace(' ', '', $normalized);
        $matches = [];

        foreach ($socios as $socio) {
            $taxCode = str_replace(' ', '', $this->normalize((string) $socio->codice_fiscale));
            $memberCode = str_replace(' ', '', $this->normalize((string) $socio->codice_socio));
            $name = $this->normalize((string) $socio->nome);
            $surname = $this->normalize((string) $socio->cognome);

            if (strlen($taxCode) === 16 && str_contains($compact, $taxCode)) {
                $matches[] = [$socio->id, 100, 'Codice fiscale'];
            } elseif (strlen($memberCode) >= 4 && str_contains($compact, $memberCode)) {
                $matches[] = [$socio->id, 95, 'Codice socio'];
            } elseif (strlen($name) >= 2 && strlen($surname) >= 2
                && str_contains($normalized, $name) && str_contains($normalized, $surname)) {
                $matches[] = [$socio->id, 85, 'Nome e cognome'];
            }
        }

        usort($matches, fn (array $a, array $b): int => $b[1] <=> $a[1]);

        if ($matches === [] || (isset($matches[1]) && $matches[0][1] === $matches[1][1])) {
            return ['socio_id' => null, 'confidence' => 0, 'reason' => 'Nessuna associazione univoca'];
        }

        return ['socio_id' => $matches[0][0], 'confidence' => $matches[0][1], 'reason' => $matches[0][2]];
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', Str::ascii(Str::upper($value))) ?? '');
    }

    private function findExecutable(string $name): ?string
    {
        $configured = config("payroll.{$name}_path");

        if (filled($configured) && is_executable($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($name);
    }
}
