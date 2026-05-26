<?php

namespace App\Console\Commands;

use App\Models\Comune;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

#[Signature('comuni:import {file? : Percorso CSV/XLS/XLSX} {--source=file : file, comuni-json oppure mlocati} {--delimiter=; : Delimitatore CSV}')]
#[Description('Importa comuni italiani da CSV, Excel o dataset pubblico matteocontrini/comuni-json.')]
class ImportComuni extends Command
{
    private const COMUNI_JSON_URL = 'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (in_array($this->option('source'), ['comuni-json', 'mlocati'], true)) {
            return $this->importFromComuniJson();
        }

        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File non trovato: {$file}");

            return self::FAILURE;
        }

        $count = 0;

        foreach ($this->rows($file) as $row) {
            $normalized = $this->normalizeRow($row);

            if (blank($normalized['codice_catastale']) || blank($normalized['denominazione'])) {
                continue;
            }

            Comune::query()->updateOrCreate(
                ['codice_catastale' => strtoupper($normalized['codice_catastale'])],
                $normalized,
            );

            $count++;
        }

        $this->info("Comuni importati/aggiornati: {$count}");

        return self::SUCCESS;
    }

    private function importFromComuniJson(): int
    {
        try {
            $response = Http::timeout(30)->get(self::COMUNI_JSON_URL);
        } catch (Throwable $exception) {
            $this->error("Impossibile scaricare il dataset comuni-json: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Impossibile scaricare il dataset comuni-json: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $municipalities = $response->json();

        if (! is_array($municipalities)) {
            $this->error('Dataset comuni-json non valido.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($municipalities as $municipality) {
            $normalized = $this->normalizeComuniJsonRow($municipality);

            if (blank($normalized['codice_catastale']) || blank($normalized['denominazione'])) {
                continue;
            }

            Comune::query()->updateOrCreate(
                ['codice_catastale' => $normalized['codice_catastale']],
                $normalized,
            );

            $count++;
        }

        $this->info("Comuni importati/aggiornati da comuni-json: {$count}");

        return self::SUCCESS;
    }

    private function rows(string $file): array
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheet = IOFactory::load($file)->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);
            $headers = array_map($this->normalizeHeader(...), array_shift($data));

            return array_map(fn (array $row): array => array_combine($headers, array_values($row)), $data);
        }

        $csv = Reader::createFromPath($file);
        $csv->setDelimiter((string) $this->option('delimiter'));
        $csv->setHeaderOffset(0);

        return iterator_to_array($csv->getRecords());
    }

    private function normalizeRow(array $row): array
    {
        $row = collect($row)
            ->mapWithKeys(fn ($value, $key): array => [$this->normalizeHeader((string) $key) => $value])
            ->all();

        return [
            'progressivo' => $row['progressivo'] ?? $row['prog'] ?? null,
            'denominazione' => $row['denominazione_comune'] ?? $row['denominazione_italiana'] ?? $row['denominazione'] ?? $row['nome'] ?? $row['comune'] ?? null,
            'ripartizione_geografica' => $row['ripartizione_geografica'] ?? $row['ripartizione'] ?? null,
            'regione' => $row['regione'] ?? $row['denominazione_regione'] ?? null,
            'provincia_unita_territoriale' => $row['provincia_unita_territoriale'] ?? $row['provincia'] ?? $row['denominazione_provincia'] ?? $row['sigla_provincia'] ?? null,
            'codice_catastale' => strtoupper((string) ($row['codice_catastale'] ?? $row['codice_belfiore'] ?? $row['belfiore'] ?? $row['codice'] ?? '')),
        ];
    }

    private function normalizeComuniJsonRow(mixed $row): array
    {
        if (! is_array($row)) {
            return [
                'progressivo' => null,
                'denominazione' => null,
                'ripartizione_geografica' => null,
                'regione' => null,
                'provincia_unita_territoriale' => null,
                'codice_catastale' => null,
            ];
        }

        return [
            'progressivo' => $row['codice'] ?? null,
            'denominazione' => $row['nome'] ?? null,
            'ripartizione_geografica' => data_get($row, 'zona.nome'),
            'regione' => data_get($row, 'regione.nome'),
            'provincia_unita_territoriale' => data_get($row, 'provincia.nome') ?? $row['sigla'] ?? null,
            'codice_catastale' => strtoupper((string) ($row['codiceCatastale'] ?? '')),
        ];
    }

    private function normalizeHeader(string $header): string
    {
        return str($header)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
