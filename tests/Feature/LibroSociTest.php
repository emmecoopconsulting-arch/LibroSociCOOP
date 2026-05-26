<?php

namespace Tests\Feature;

use App\Filament\Pages\IntestazioneDocumenti;
use App\Models\Comune;
use App\Models\DocumentHeaderSetting;
use App\Models\Socio;
use App\Models\SocioWorkContract;
use App\Models\User;
use App\Models\Verbale;
use App\Services\SocioVariationService;
use App\Services\VerbalePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibroSociTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_pages_are_available_to_admin(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/libro-soci')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/gestione-verbali')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/intestazione-documenti')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/socio-variations')
            ->assertOk();
    }

    public function test_document_header_settings_can_be_saved_from_filament_page(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        Livewire::actingAs($admin)
            ->test(IntestazioneDocumenti::class)
            ->set('data.text', "FTM Cooperativa Sociale\nUnità locale: Novi Ligure")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(DocumentHeaderSetting::class, [
            'text' => "FTM Cooperativa Sociale\nUnità locale: Novi Ligure",
        ]);
    }

    public function test_codice_fiscale_data_is_applied_and_admission_pdf_is_saved(): void
    {
        Storage::fake('local');

        Comune::create([
            'denominazione' => 'Roma',
            'regione' => 'Lazio',
            'provincia_unita_territoriale' => 'Roma',
            'codice_catastale' => 'H501',
        ]);

        $socio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $verbale = app(VerbalePdfService::class)->generateAdmission($socio);

        $this->assertSame('SOC-0001', $socio->codice_socio);
        $this->assertSame('1980-01-01', $socio->data_nascita->format('Y-m-d'));
        $this->assertSame('Roma', $socio->luogo_nascita);
        $this->assertSame('generato', $verbale->stato);
        Storage::disk('local')->assertExists($verbale->file_path);
    }

    public function test_admission_social_summary_uses_values_before_current_member(): void
    {
        Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Primo',
            'cognome' => 'Socio',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-20',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Secondo',
            'cognome' => 'Socio',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-21',
            'stato' => 'attivo',
            'capitale_versato' => 200,
        ]);

        $socio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Nuovo',
            'cognome' => 'Socio',
            'codice_fiscale' => 'BNCLGU80A01H501X',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 150,
        ]);

        $verbale = Verbale::create([
            'socio_id' => $socio->id,
            'tipo' => 'ammissione',
            'stato' => 'da_generare',
            'titolo' => 'Verbale test',
            'data_verbale' => '2026-05-25',
        ]);

        $this->assertSame([
            'soci_ordinari_prima' => 2,
            'capitale_sociale_prima' => 300.0,
            'soci_ordinari_entrati' => 1,
            'soci_ordinari_usciti' => 0,
            'soci_ordinari_complessivi' => 3,
            'capitale_sociale_entrato' => 150.0,
            'capitale_sociale_uscito' => 0.0,
            'capitale_sociale_complessivo' => 450.0,
        ], app(VerbalePdfService::class)->riepilogoSociale($verbale, $socio));
    }

    public function test_omocodia_codice_fiscale_is_decoded_for_birth_place(): void
    {
        Comune::create([
            'denominazione' => 'Roma',
            'regione' => 'Lazio',
            'provincia_unita_territoriale' => 'Roma',
            'codice_catastale' => 'H501',
        ]);

        $socio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80AL1H5L1Q',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $this->assertSame('1980-01-01', $socio->data_nascita->format('Y-m-d'));
        $this->assertSame('Roma', $socio->luogo_nascita);
    }

    public function test_public_dataset_import_loads_cadastral_codes(): void
    {
        $this->artisan('comuni:import', ['--source' => 'mlocati'])
            ->assertSuccessful();

        $this->assertDatabaseHas('comunes', [
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'regione' => 'Lazio',
        ]);
    }

    public function test_libro_soci_exports_are_downloadable_via_http_routes(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        Comune::create([
            'denominazione' => 'Roma',
            'regione' => 'Lazio',
            'provincia_unita_territoriale' => 'Roma',
            'codice_catastale' => 'H501',
        ]);

        Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $this->actingAs($admin)
            ->get(route('exports.libro-soci.pdf'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->get(route('exports.libro-soci.excel'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_guided_work_variation_creates_verbale_and_contract(): void
    {
        $socio = Socio::create([
            'tipologia' => 'lavoratore',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $variation = app(SocioVariationService::class)->createAndApply([
            'socio_id' => $socio->id,
            'tipo' => 'variazione_contratto',
            'data_verbale' => '2026-05-26',
            'data_effetto' => '2026-06-01',
            'tipo_contratto' => 'determinato',
            'data_inizio' => '2026-06-01',
            'data_fine' => '2026-12-31',
            'ore_settimanali' => 30,
            'note' => 'Prima formalizzazione contratto.',
        ]);

        $this->assertSame('applicata', $variation->stato);
        $this->assertSame('variazione_contratto', $variation->verbale->tipo);
        $contract = SocioWorkContract::query()
            ->where('socio_id', $socio->id)
            ->where('verbale_id', $variation->verbale_id)
            ->firstOrFail();

        $this->assertDatabaseHas(SocioWorkContract::class, [
            'socio_id' => $socio->id,
            'verbale_id' => $variation->verbale_id,
            'tipo_contratto' => 'determinato',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);
        $this->assertSame('2026-06-01', $contract->data_inizio->format('Y-m-d'));
        $this->assertSame('2026-12-31', $contract->data_fine->format('Y-m-d'));
    }

    public function test_work_termination_variation_closes_contract_and_member_status(): void
    {
        $socio = Socio::create([
            'tipologia' => 'lavoratore',
            'nome' => 'Luigi',
            'cognome' => 'Verdi',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $contract = SocioWorkContract::create([
            'socio_id' => $socio->id,
            'tipo_contratto' => 'determinato',
            'data_inizio' => '2026-06-01',
            'data_fine' => '2026-12-31',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);

        app(SocioVariationService::class)->createAndApply([
            'socio_id' => $socio->id,
            'tipo' => 'cessazione_rapporto',
            'data_verbale' => '2026-09-20',
            'data_effetto' => '2026-09-30',
        ]);

        $this->assertSame('cessato', $contract->refresh()->stato);
        $this->assertSame('2026-09-30', $contract->data_fine->format('Y-m-d'));
        $this->assertSame('recesso', $socio->refresh()->stato);
        $this->assertSame('2026-09-30', $socio->data_uscita->format('Y-m-d'));
    }

    public function test_pending_variation_verbales_are_generated_with_generic_template(): void
    {
        Storage::fake('local');

        $socio = Socio::create([
            'tipologia' => 'lavoratore',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        SocioWorkContract::create([
            'socio_id' => $socio->id,
            'tipo_contratto' => 'indeterminato',
            'data_inizio' => '2026-06-01',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);

        $variation = app(SocioVariationService::class)->createAndApply([
            'socio_id' => $socio->id,
            'tipo' => 'variazione_ore',
            'data_verbale' => '2026-06-15',
            'data_effetto' => '2026-07-01',
            'ore_settimanali' => 32,
        ]);

        $verbale = app(VerbalePdfService::class)->generate($variation->verbale);

        $this->assertSame('generato', $verbale->stato);
        Storage::disk('local')->assertExists($verbale->file_path);
    }
}
