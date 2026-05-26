<?php

namespace App\Console\Commands;

use App\Models\Comune;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use League\Csv\Reader;
use MLocati\ComuniItaliani\Factory;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Signature('comuni:import {file? : Percorso CSV/XLS/XLSX} {--source=file : file oppure mlocati} {--delimiter=; : Delimitatore CSV}')]
#[Description('Importa comuni italiani da CSV, Excel o dataset pubblico mlocati/comuni-italiani.')]
class ImportComuni extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('source') === 'mlocati') {
            return $this->importFromMlocati();
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

    private function importFromMlocati(): int
    {
        $count = 0;

        foreach ((new Factory)->getMunicipalities() as $municipality) {
            $province = $municipality->getProvince();
            $region = $province->getRegion();

            Comune::query()->updateOrCreate(
                ['codice_catastale' => $municipality->getCadastralCode()],
                [
                    'progressivo' => null,
                    'denominazione' => $municipality->getName(),
                    'ripartizione_geografica' => $region->getGeographicalSubdivision()->getName(),
                    'regione' => $region->getName(),
                    'provincia_unita_territoriale' => $province->getName(),
                    'codice_catastale' => $municipality->getCadastralCode(),
                ],
            );

            $count++;
        }

        $this->info("Comuni importati/aggiornati da dataset pubblico: {$count}");

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
