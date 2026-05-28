<?php

namespace App\Services;

use App\Models\Socio;
use App\Rules\CodiceFiscale;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SocioExcelImportService
{
    public const FIELDS = [
        'codice_socio' => 'Codice socio',
        'nome' => 'Nome',
        'cognome' => 'Cognome',
        'codice_fiscale' => 'Codice fiscale',
        'tipologia' => 'Tipologia',
        'stato' => 'Stato',
        'data_ammissione' => 'Data ammissione',
        'quota_sociale' => 'Quota sociale',
        'capitale_versato' => 'Capitale versato',
        'comune_residenza' => 'Comune residenza',
        'indirizzo' => 'Indirizzo',
        'telefono' => 'Telefono',
        'email' => 'Email',
        'note' => 'Note',
    ];

    private const REQUIRED_FIELDS = ['nome', 'cognome', 'codice_fiscale'];

    private const HEADER_GUESSES = [
        'codice_socio' => ['codice socio', 'codice', 'matricola', 'numero socio'],
        'nome' => ['nome'],
        'cognome' => ['cognome'],
        'codice_fiscale' => ['codice fiscale', 'cf', 'c.f.', 'c f'],
        'tipologia' => ['tipologia', 'tipo socio', 'categoria'],
        'stato' => ['stato', 'stato socio'],
        'data_ammissione' => ['data ammissione', 'ammesso il', 'ammissione'],
        'quota_sociale' => ['quota sociale', 'quota'],
        'capitale_versato' => ['capitale versato', 'capitale'],
        'comune_residenza' => ['comune residenza', 'residenza', 'citta', 'città'],
        'indirizzo' => ['indirizzo', 'via', 'indirizzo residenza'],
        'telefono' => ['telefono', 'cellulare', 'tel'],
        'email' => ['email', 'e-mail', 'mail'],
        'note' => ['note', 'annotazioni'],
    ];

    /**
     * @return array<string>
     */
    public function sheetNames(string $path): array
    {
        return IOFactory::load($path)->getSheetNames();
    }

    /**
     * @return array<string, string>
     */
    public function columnOptions(string $path, ?string $sheetName = null, bool $firstRowContainsHeaders = true): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = filled($sheetName) ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        if (! $sheet) {
            return [];
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $options = [];

        for ($index = 1; $index <= $highestColumnIndex; $index++) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $heading = $firstRowContainsHeaders ? trim((string) $sheet->getCell([$index, 1])->getFormattedValue()) : '';

            $options[$this->columnOptionKey($index - 1)] = filled($heading) ? "{$letter} - {$heading}" : $letter;
        }

        return $options;
    }

    /**
     * @return array<string, string|null>
     */
    public function guessedMapping(string $path, ?string $sheetName = null): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = filled($sheetName) ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        if (! $sheet) {
            return array_fill_keys(array_keys(self::FIELDS), null);
        }

        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($index = 1; $index <= $highestColumnIndex; $index++) {
            $headers[$index - 1] = $this->normalizeHeader((string) $sheet->getCell([$index, 1])->getFormattedValue());
        }

        $mapping = array_fill_keys(array_keys(self::FIELDS), null);

        foreach (self::HEADER_GUESSES as $field => $guesses) {
            foreach ($guesses as $guess) {
                $column = array_search($this->normalizeHeader($guess), $headers, true);

                if ($column !== false) {
                    $mapping[$field] = $this->columnOptionKey($column);
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array{rows: array<int, array<string, mixed>>, total_rows: int, valid_rows: int, invalid_rows: int}
     */
    public function preview(string $path, array $mapping, ?string $sheetName = null, bool $firstRowContainsHeaders = true, bool $updateExisting = false, int $limit = 10): array
    {
        $rows = $this->mappedRows($path, $mapping, $sheetName, $firstRowContainsHeaders, $limit);
        $previewRows = [];

        foreach ($rows as $row) {
            $validation = $this->validateRow($row['data'], $updateExisting);

            $previewRows[] = [
                'number' => $row['number'],
                'data' => $row['data'],
                'errors' => $validation,
            ];
        }

        return [
            'rows' => $previewRows,
            'total_rows' => $this->countMappedRows($path, $mapping, $sheetName, $firstRowContainsHeaders),
            'valid_rows' => collect($previewRows)->where('errors', [])->count(),
            'invalid_rows' => collect($previewRows)->reject(fn (array $row): bool => $row['errors'] === [])->count(),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(string $path, array $mapping, ?string $sheetName = null, bool $firstRowContainsHeaders = true, bool $updateExisting = false): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($this->mappedRows($path, $mapping, $sheetName, $firstRowContainsHeaders) as $row) {
            $rowErrors = $this->validateRow($row['data'], $updateExisting);

            if ($rowErrors !== []) {
                $skipped++;
                $errors[] = "Riga {$row['number']}: ".implode(' ', $rowErrors);

                continue;
            }

            $attributes = $this->prepareAttributes($row['data']);
            $existing = Socio::query()
                ->where('codice_fiscale', $attributes['codice_fiscale'])
                ->first();

            if ($existing) {
                if (! $updateExisting) {
                    $skipped++;

                    continue;
                }

                if (blank($attributes['codice_socio'] ?? null)) {
                    unset($attributes['codice_socio']);
                }

                $existing->update($attributes);
                $updated++;

                continue;
            }

            Socio::query()->create($attributes);
            $created++;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array<int, array{number: int, data: array<string, mixed>}>
     */
    private function mappedRows(string $path, array $mapping, ?string $sheetName, bool $firstRowContainsHeaders, ?int $limit = null): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = filled($sheetName) ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        if (! $sheet) {
            return [];
        }

        $rows = [];
        $startRow = $firstRowContainsHeaders ? 2 : 1;
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = $startRow; $rowNumber <= $highestRow; $rowNumber++) {
            $data = [];

            foreach (self::FIELDS as $field => $label) {
                $columnIndex = $mapping[$field] ?? null;

                if ($columnIndex === null || $columnIndex === '') {
                    $data[$field] = null;

                    continue;
                }

                $cell = $sheet->getCell([$this->columnIndex($columnIndex) + 1, $rowNumber]);
                $data[$field] = $this->cellValue($cell->getValue(), $cell->getFormattedValue(), $field);
            }

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rows[] = [
                'number' => $rowNumber,
                'data' => $data,
            ];

            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     */
    private function countMappedRows(string $path, array $mapping, ?string $sheetName, bool $firstRowContainsHeaders): int
    {
        return count($this->mappedRows($path, $mapping, $sheetName, $firstRowContainsHeaders));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function validateRow(array $data, bool $updateExisting): array
    {
        $validator = Validator::make($this->prepareAttributes($data), [
            'codice_socio' => ['nullable', 'string', 'max:20'],
            'nome' => ['required', 'string', 'max:255'],
            'cognome' => ['required', 'string', 'max:255'],
            'codice_fiscale' => ['required', 'string', 'size:16', new CodiceFiscale],
            'tipologia' => ['required', 'in:'.implode(',', array_keys(Socio::TIPOLOGIE))],
            'stato' => ['required', 'in:'.implode(',', array_keys(Socio::STATI))],
            'data_ammissione' => ['nullable', 'date'],
            'data_uscita' => ['nullable', 'date'],
            'quota_sociale' => ['nullable', 'numeric'],
            'capitale_versato' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], self::FIELDS);

        $errors = $validator->errors()->all();
        $codiceFiscale = (string) Arr::get($this->prepareAttributes($data), 'codice_fiscale');

        if (! $updateExisting && filled($codiceFiscale) && Socio::query()->where('codice_fiscale', $codiceFiscale)->exists()) {
            $errors[] = 'Codice fiscale già presente.';
        }

        $codiceSocio = (string) Arr::get($this->prepareAttributes($data), 'codice_socio');

        if (filled($codiceSocio)) {
            $query = Socio::query()->where('codice_socio', $codiceSocio);

            if (filled($codiceFiscale)) {
                $query->where('codice_fiscale', '!=', $codiceFiscale);
            }

            if ($query->exists()) {
                $errors[] = 'Codice socio già presente su un altro socio.';
            }
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank($data[$field] ?? null)) {
                $errors[] = self::FIELDS[$field].' non mappato o vuoto.';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareAttributes(array $data): array
    {
        return [
            'codice_socio' => filled($data['codice_socio'] ?? null) ? (string) $data['codice_socio'] : null,
            'nome' => filled($data['nome'] ?? null) ? Str::title((string) $data['nome']) : null,
            'cognome' => filled($data['cognome'] ?? null) ? Str::title((string) $data['cognome']) : null,
            'codice_fiscale' => filled($data['codice_fiscale'] ?? null) ? strtoupper((string) $data['codice_fiscale']) : null,
            'tipologia' => $this->normalizeOption((string) ($data['tipologia'] ?? ''), Socio::TIPOLOGIE, 'ordinario'),
            'stato' => $this->normalizeOption((string) ($data['stato'] ?? ''), Socio::STATI, 'attivo'),
            'data_ammissione' => $this->normalizeDate($data['data_ammissione'] ?? null),
            'quota_sociale' => $this->normalizeDecimal($data['quota_sociale'] ?? null),
            'capitale_versato' => $this->normalizeDecimal($data['capitale_versato'] ?? null),
            'comune_residenza' => filled($data['comune_residenza'] ?? null) ? (string) $data['comune_residenza'] : null,
            'indirizzo' => filled($data['indirizzo'] ?? null) ? (string) $data['indirizzo'] : null,
            'telefono' => filled($data['telefono'] ?? null) ? (string) $data['telefono'] : null,
            'email' => filled($data['email'] ?? null) ? Str::lower((string) $data['email']) : null,
            'note' => filled($data['note'] ?? null) ? (string) $data['note'] : null,
        ];
    }

    private function cellValue(mixed $rawValue, string $formattedValue, string $field): mixed
    {
        if (in_array($field, ['data_ammissione'], true) && is_numeric($rawValue)) {
            return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
        }

        return trim($formattedValue);
    }

    private function columnOptionKey(int $index): string
    {
        return "column_{$index}";
    }

    private function columnIndex(int|string $value): int
    {
        if (is_string($value) && str_starts_with($value, 'column_')) {
            return (int) str($value)->after('column_')->toString();
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        return collect($data)
            ->filter(fn (mixed $value): bool => filled($value))
            ->isEmpty();
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<string, string>  $options
     */
    private function normalizeOption(string $value, array $options, string $default): string
    {
        if (blank($value)) {
            return $default;
        }

        $normalized = $this->normalizeHeader($value);

        foreach ($options as $key => $label) {
            if ($normalized === $this->normalizeHeader($key) || $normalized === $this->normalizeHeader($label)) {
                return $key;
            }
        }

        return $normalized;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, (string) $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function normalizeDecimal(mixed $value): float
    {
        if (blank($value)) {
            return 0;
        }

        $normalized = preg_replace('/[^\d,.-]/', '', (string) $value) ?: '0';

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }
}
