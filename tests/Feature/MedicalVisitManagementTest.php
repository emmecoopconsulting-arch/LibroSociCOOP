<?php

namespace Tests\Feature;

use App\Filament\Pages\Scadenziario;
use App\Filament\Pages\VisiteMediche;
use App\Filament\Resources\Socios\Pages\EditSocio;
use App\Filament\Resources\Socios\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Socios\RelationManagers\MedicalVisitsRelationManager;
use App\Models\AppSetting;
use App\Models\Socio;
use App\Models\SocioMedicalVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalVisitManagementTest extends TestCase
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
            'nome' => 'Mario', 'cognome' => 'Rossi',
            'codice_fiscale' => strtoupper(fake()->unique()->bothify('????????????????')),
            'tipologia' => 'ordinario', 'stato' => 'attivo',
            'data_ammissione' => '2025-01-01',
        ]);
    }

    private function visit(Socio $socio, array $attributes = []): SocioMedicalVisit
    {
        return $socio->medicalVisits()->create($attributes + [
            'data_visita' => '2026-01-01', 'scadenza' => '2027-01-01',
        ]);
    }

    public function test_pdf_document_is_created_updated_and_removed_with_visit(): void
    {
        $socio = $this->socio();
        $visit = $this->visit($socio);
        $this->assertDatabaseCount('socio_documents', 0);
        Storage::disk('local')->put('visite-mediche/original.pdf', '%PDF-1.4');
        $visit->update(['pdf_path' => 'visite-mediche/original.pdf']);
        $documentId = $visit->document()->sole()->id;
        $visit->update(['scadenza' => '2026-12-01', 'note' => 'Controllo periodico']);
        $visit->syncDocument();
        $this->assertDatabaseCount('socio_documents', 1);
        $this->assertDatabaseHas('socio_documents', [
            'id' => $documentId, 'socio_id' => $socio->id, 'socio_medical_visit_id' => $visit->id,
            'tipo' => 'visita_medica', 'file_path' => 'visite-mediche/original.pdf',
            'data_scadenza' => '2026-12-01 00:00:00', 'note' => 'Controllo periodico',
        ]);
        $visit->update(['pdf_path' => 'visite-mediche/replaced.pdf']);
        $this->assertSame($documentId, $visit->document()->sole()->id);
        $this->assertSame('visite-mediche/replaced.pdf', $visit->document()->sole()->file_path);
        $visit->update(['pdf_path' => null]);
        $this->assertDatabaseCount('socio_documents', 0);
        Storage::disk('local')->assertExists('visite-mediche/original.pdf');
        $visit->update(['pdf_path' => 'visite-mediche/original.pdf']);
        $visit->delete();
        $this->assertDatabaseCount('socio_documents', 0);
    }

    public function test_retroactive_migration_recovers_all_visits_without_duplicating_files_or_documents(): void
    {
        $migration = require database_path('migrations/2026_09_11_000001_link_medical_visit_documents.php');
        $migration->down();
        $socio = $this->socio(['stato' => 'recesso']);
        $values = ['socio_id' => $socio->id, 'data_visita' => '2025-01-01', 'scadenza' => '2026-01-01', 'note' => 'Storico'];
        $firstId = DB::table('socio_medical_visits')->insertGetId($values + ['pdf_path' => 'visite-mediche/old.pdf']);
        $secondId = DB::table('socio_medical_visits')->insertGetId($values + ['pdf_path' => 'visite-mediche/second.pdf']);
        DB::table('socio_medical_visits')->insert($values + ['pdf_path' => null]);
        DB::table('socio_medical_visits')->insert($values + ['pdf_path' => '']);
        Storage::disk('local')->put('visite-mediche/old.pdf', '%PDF-1.4');
        $existingId = DB::table('socio_documents')->insertGetId([
            'socio_id' => $socio->id, 'tipo' => 'visita_medica', 'file_path' => 'visite-mediche/old.pdf',
        ]);
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('socio_documents', 2);
        $this->assertDatabaseHas('socio_documents', ['id' => $existingId, 'socio_medical_visit_id' => $firstId]);
        $this->assertDatabaseHas('socio_documents', ['socio_medical_visit_id' => $secondId, 'file_path' => 'visite-mediche/second.pdf']);
        $this->assertSame(['visite-mediche/old.pdf'], Storage::disk('local')->allFiles());
    }

    public function test_dashboard_filters_latest_visit_by_date_and_separates_missing_pdf(): void
    {
        AppSetting::setValue(AppSetting::VISITA_MEDICA_ALERT_DAYS, 30);
        $missing = $this->socio(['cognome' => 'SenzaVisita']);
        $overdue = $this->socio(['cognome' => 'Scaduto']);
        $today = $this->socio(['cognome' => 'Oggi']);
        $boundary = $this->socio(['cognome' => 'Limite']);
        $valid = $this->socio(['cognome' => 'Valido']);
        $inactive = $this->socio(['stato' => 'recesso']);
        $volunteer = $this->socio(['tipologia' => 'volontario']);
        $deleted = $this->socio();
        $deleted->delete();
        $this->visit($overdue, ['scadenza' => '2026-09-10']);
        $this->visit($today, ['scadenza' => '2026-09-11']);
        $this->visit($boundary, ['scadenza' => '2026-10-11']);
        $latest = $this->visit($valid, ['data_visita' => '2026-09-01', 'scadenza' => '2026-10-12', 'pdf_path' => 'visite-mediche/valid.pdf']);
        $this->visit($valid, ['data_visita' => '2024-09-01', 'scadenza' => '2025-09-01']);
        $page = Livewire::test(VisiteMediche::class)
            ->assertCanSeeTableRecords([$missing, $overdue, $today, $boundary, $valid])
            ->assertCanNotSeeTableRecords([$inactive, $volunteer, $deleted])
            ->sortTable('latestMedicalVisit.scadenza');
        $this->assertSame(['missing' => 1, 'overdue' => 1, 'expiring' => 2, 'valid' => 1, 'without_pdf' => 3], $page->instance()->overview());
        $page->filterTable('visit_status', 'missing')->assertCanSeeTableRecords([$missing])->assertCountTableRecords(1)
            ->filterTable('visit_status', 'overdue')->assertCanSeeTableRecords([$overdue])->assertCountTableRecords(1)
            ->filterTable('visit_status', 'expiring')->assertCanSeeTableRecords([$today, $boundary])->assertCountTableRecords(2)
            ->filterTable('visit_status', 'valid')->assertCanSeeTableRecords([$valid])->assertCountTableRecords(1)
            ->filterTable('visit_status', null)->filterTable('without_pdf')->assertCanSeeTableRecords([$overdue, $today, $boundary])->assertCountTableRecords(3);
        $page->call('selectOverviewFilter', 'missing')->assertCanSeeTableRecords([$missing])->assertCountTableRecords(1)
            ->call('selectOverviewFilter', 'without_pdf')->assertCanSeeTableRecords([$overdue, $today, $boundary])->assertCountTableRecords(3);
        $this->assertSame($latest->id, $valid->fresh()->latestMedicalVisit->id);
        $this->assertSame([$latest->id], SocioMedicalVisit::query()->latestForSocios()->where('socio_id', $valid->id)->pluck('id')->all());
        $calendar = Livewire::test(Scadenziario::class)->set('month', '2026-10-01');
        $this->assertArrayHasKey('2026-10-12', $calendar->instance()->eventsByDay());
    }

    public function test_same_day_visits_use_latest_id_as_tie_breaker(): void
    {
        $socio = $this->socio();
        $this->visit($socio, ['scadenza' => '2026-09-01']);
        $latest = $this->visit($socio);
        $this->assertSame($latest->id, $socio->latestMedicalVisit->id);
        $this->assertSame([$latest->id], SocioMedicalVisit::query()->latestForSocios()->pluck('id')->all());
    }

    public function test_registration_accepts_custom_deadline_and_rejects_deadline_before_visit(): void
    {
        $socio = $this->socio();
        Livewire::test(VisiteMediche::class)->fillForm([
            'socio_ids' => [$socio->id], 'data_visita' => '2026-09-01', 'scadenza' => '2026-08-01',
        ])->call('save')->assertHasFormErrors(['scadenza'])
            ->fillForm(['scadenza' => '2027-03-01'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('2027-03-01', $socio->medicalVisits()->sole()->scadenza->toDateString());
    }

    public function test_manage_action_updates_visit_and_attachment_and_history_is_available(): void
    {
        $socio = $this->socio();
        $visit = $this->visit($socio, ['pdf_path' => 'visite-mediche/test.pdf']);
        Storage::disk('local')->put('visite-mediche/test.pdf', '%PDF-1.4');
        Livewire::test(VisiteMediche::class)->callTableAction('editVisit', $socio, data: [
            'data_visita' => '2026-01-01', 'scadenza' => '2026-12-20', 'note' => 'Aggiornata',
            'pdf_path' => ['visite-mediche/test.pdf'],
        ])->assertHasNoErrors();
        $this->assertSame('2026-12-20', $visit->fresh()->scadenza->toDateString());
        $this->assertSame('2026-12-20', $visit->document()->sole()->data_scadenza->toDateString());
        Livewire::test(MedicalVisitsRelationManager::class, ['ownerRecord' => $socio, 'pageClass' => EditSocio::class])
            ->assertCanSeeTableRecords([$visit])->searchTable('Mario')->assertCanSeeTableRecords([$visit]);
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $socio, 'pageClass' => EditSocio::class])
            ->assertCanSeeTableRecords([$visit->document()->sole()])
            ->assertTableActionHidden('edit', $visit->document()->sole())
            ->assertTableActionHidden('delete', $visit->document()->sole())
            ->callTableAction('manageVisit', $visit->document()->sole(), data: [
                'data_visita' => '2026-01-01', 'scadenza' => '2026-11-20',
                'pdf_path' => ['visite-mediche/test.pdf'],
            ])->assertHasNoErrors();
        $this->assertSame('2026-11-20', $visit->fresh()->scadenza->toDateString());
        $this->assertSame('2026-11-20', $visit->document()->sole()->data_scadenza->toDateString());
        $this->get(route('visite-mediche.download', $visit))->assertOk();
    }
}
