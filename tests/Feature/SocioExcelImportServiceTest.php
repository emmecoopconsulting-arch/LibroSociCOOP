<?php

namespace Tests\Feature;

use App\Models\Socio;
use App\Services\SocioExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class SocioExcelImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_guesses_columns_and_imports_valid_rows(): void
    {
        $path = $this->makeWorkbook([
            ['Nome', 'Cognome', 'Codice fiscale', 'Tipologia', 'Stato', 'Data ammissione', 'Capitale versato'],
            ['Mario', 'Rossi', 'RSSMRA80A01H501U', 'Ordinario', 'Attivo', '15/05/2026', '1.250,50'],
        ]);

        $service = app(SocioExcelImportService::class);
        $mapping = $service->guessedMapping($path);

        $this->assertSame('0', $mapping['nome']);
        $this->assertSame('1', $mapping['cognome']);
        $this->assertSame('2', $mapping['codice_fiscale']);

        $preview = $service->preview($path, $mapping);

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame([], $preview['rows'][0]['errors']);

        $result = $service->import($path, $mapping);

        $this->assertSame(1, $result['created']);
        $socio = Socio::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->firstOrFail();

        $this->assertSame('Mario', $socio->nome);
        $this->assertSame('Rossi', $socio->cognome);
        $this->assertSame('ordinario', $socio->tipologia);
        $this->assertSame('attivo', $socio->stato);
        $this->assertSame('2026-05-15', $socio->data_ammissione->toDateString());
        $this->assertSame(1250.50, (float) $socio->capitale_versato);
    }

    public function test_it_skips_existing_codice_fiscale_unless_updates_are_enabled(): void
    {
        Socio::query()->create([
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'tipologia' => 'ordinario',
            'stato' => 'attivo',
        ]);

        $path = $this->makeWorkbook([
            ['Nome', 'Cognome', 'Codice fiscale', 'Telefono'],
            ['Mario', 'Rossi', 'RSSMRA80A01H501U', '3331234567'],
        ]);

        $service = app(SocioExcelImportService::class);
        $mapping = $service->guessedMapping($path);

        $result = $service->import($path, $mapping);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);

        $result = $service->import($path, $mapping, updateExisting: true);

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('socios', [
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'telefono' => '3331234567',
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeWorkbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soci-import-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        return $path;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function sheetXml(array $rows): string
    {
        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $coordinate = $this->columnName($columnIndex + 1).$rowNumber;
                $escapedValue = htmlspecialchars((string) $value, ENT_XML1);
                $cells .= "<c r=\"{$coordinate}\" t=\"inlineStr\"><is><t>{$escapedValue}</t></is></c>";
            }

            $sheetRows .= "<row r=\"{$rowNumber}\">{$cells}</row>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>{$sheetRows}</sheetData>
</worksheet>
XML;
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
