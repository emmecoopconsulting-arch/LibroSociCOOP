<?php

namespace Tests\Feature;

use App\Filament\Pages\VisiteMediche;
use App\Filament\Resources\SocioMedicalVisitBatches\Pages\EditSocioMedicalVisitBatch;
use App\Filament\Resources\SocioMedicalVisitBatches\Pages\ListSocioMedicalVisitBatches;
use App\Filament\Resources\SocioMedicalVisitBatches\RelationManagers\VisitsRelationManager;
use App\Filament\Resources\Socios\Pages\EditSocio;
use App\Filament\Resources\Socios\RelationManagers\MedicalVisitsRelationManager;
use App\Models\Socio;
use App\Models\SocioMedicalVisitBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalVisitExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 11)->startOfDay());
        Storage::fake('local');
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');
        $this->actingAs($admin);
    }

    private function socio(array $attributes = []): Socio
    {
        return Socio::create($attributes + [
            'nome' => 'Mario', 'cognome' => 'Esporta',
            'codice_fiscale' => strtoupper(fake()->unique()->bothify('????????????????')),
            'tipologia' => 'ordinario', 'stato' => 'attivo', 'data_ammissione' => '2025-01-01',
        ]);
    }

    private function workbook($page, string $filename): Spreadsheet
    {
        $page->callTableAction('exportExcel')->assertHasNoErrors()
            ->assertFileDownloaded($filename.'-2026-09-11_000000.xlsx', contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'medical-export-');
        try {
            file_put_contents($path, base64_decode($page->effects['download']['content']));

            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_overview_exports_all_filtered_rows_in_display_order_and_only_visible_columns(): void
    {
        $members = collect();
        for ($i = 0; $i < 12; $i++) {
            $members->push($this->socio());
        }
        $this->socio(['cognome' => 'Diverso']);
        $this->socio(['stato' => 'recesso']);
        $visited = $this->socio();
        $visited->medicalVisits()->create(['data_visita' => '2026-01-01', 'scadenza' => '2027-01-01']);
        $page = Livewire::test(VisiteMediche::class)->filterTable('visit_status', 'missing')
            ->searchTable('Esporta')->sortTable('codice_socio', 'desc')->toggleAllTableColumns(false);
        $book = $this->workbook($page, 'situazione-visite-mediche');
        $sheet = $book->getActiveSheet();
        $this->assertSame(13, $sheet->getHighestDataRow());
        $this->assertSame($members->last()->codice_socio, $sheet->getCell('A2')->getValue());
        $this->assertSame($members->first()->codice_socio, $sheet->getCell('A13')->getValue());
        $this->assertNotContains('Mansione', $sheet->rangeToArray('A1:H1')[0]);
        $this->assertSame('Nessuna visita', $sheet->getCell('C2')->getValue());
        $this->assertNull($sheet->getCell('D2')->getValue());
        $this->assertSame('No', $sheet->getCell('G2')->getValue());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:G13', $sheet->getAutoFilter()->getRange());
        $book->disconnectWorksheets();
    }

    public function test_overview_keeps_dates_as_excel_dates_and_text_as_text(): void
    {
        $socio = $this->socio(['nome' => '=1+1']);
        $socio->medicalVisits()->create(['data_visita' => '2026-09-01', 'scadenza' => '2026-09-11', 'pdf_path' => 'visite-mediche/a.pdf']);
        $book = $this->workbook(Livewire::test(VisiteMediche::class)->filterTable('visit_status', 'expiring'), 'situazione-visite-mediche');
        $sheet = $book->getActiveSheet();
        $this->assertSame('=1+1 Esporta', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('In scadenza', $sheet->getCell('D2')->getValue());
        $this->assertTrue(Date::isDateTime($sheet->getCell('F2')));
        $this->assertSame('2026-09-11', Date::excelToDateTimeObject($sheet->getCell('F2')->getValue())->format('Y-m-d'));
        $this->assertSame('Scade oggi', $sheet->getCell('G2')->getValue());
        $this->assertSame('Sì', $sheet->getCell('H2')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_batch_export_keeps_full_notes_and_zero_counts(): void
    {
        $note = '=1+1 '.str_repeat('Nota completa ', 12);
        SocioMedicalVisitBatch::create(['data_visita' => '2026-09-01', 'note' => $note]);
        SocioMedicalVisitBatch::create(['data_visita' => '2026-08-01', 'note' => 'Precedente']);
        $book = $this->workbook(Livewire::test(ListSocioMedicalVisitBatches::class), 'storico-visite-mediche');
        $sheet = $book->getActiveSheet();
        $this->assertSame(3, $sheet->getHighestDataRow());
        $this->assertSame(0, $sheet->getCell('B2')->getValue());
        $this->assertSame(0, $sheet->getCell('C2')->getValue());
        $this->assertSame($note, $sheet->getCell('D2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D2')->getDataType());
        $book->disconnectWorksheets();
    }

    public function test_detail_exports_remain_scoped_to_the_batch_or_member(): void
    {
        $batch = SocioMedicalVisitBatch::create(['data_visita' => '2026-09-01']);
        $socio = $this->socio();
        $socio->medicalVisits()->create(['batch_id' => $batch->id, 'data_visita' => '2026-09-01', 'scadenza' => '2027-09-01']);
        $socio->medicalVisits()->create(['data_visita' => '2025-09-01', 'scadenza' => '2026-09-01']);
        $other = $this->socio(['cognome' => 'Esterno']);
        $other->medicalVisits()->create(['data_visita' => '2026-09-01', 'scadenza' => '2027-09-01']);
        $batchBook = $this->workbook(Livewire::test(VisitsRelationManager::class, [
            'ownerRecord' => $batch, 'pageClass' => EditSocioMedicalVisitBatch::class,
        ]), 'dettaglio-visite-mediche');
        $this->assertSame(2, $batchBook->getActiveSheet()->getHighestDataRow());
        $batchBook->disconnectWorksheets();
        $memberBook = $this->workbook(Livewire::test(MedicalVisitsRelationManager::class, [
            'ownerRecord' => $socio, 'pageClass' => EditSocio::class,
        ]), 'dettaglio-visite-mediche');
        $this->assertSame(3, $memberBook->getActiveSheet()->getHighestDataRow());
        $this->assertSame($socio->codice_socio, $memberBook->getActiveSheet()->getCell('A3')->getValue());
        $memberBook->disconnectWorksheets();
    }

    public function test_empty_export_is_a_valid_workbook_with_headers(): void
    {
        $book = $this->workbook(Livewire::test(VisiteMediche::class), 'situazione-visite-mediche');
        $this->assertSame(1, $book->getActiveSheet()->getHighestDataRow());
        $this->assertSame('Codice', $book->getActiveSheet()->getCell('A1')->getValue());
        $book->disconnectWorksheets();
    }
}
