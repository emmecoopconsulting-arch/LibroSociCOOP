<?php

namespace Tests\Feature;

use App\Filament\Pages\IntestazioneDocumenti;
use App\Filament\Pages\Assemblea as AssembleaPage;
use App\Filament\Pages\ModelliVerbali;
use App\Filament\Resources\Socios\Pages\CreateSocio;
use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Filament\Resources\Verbales\Pages\ListVerbales;
use App\Models\Assemblea;
use App\Models\Comune;
use App\Models\DocumentHeaderSetting;
use App\Models\Socio;
use App\Models\SocioChange;
use App\Models\SocioWorkContract;
use App\Models\User;
use App\Models\Verbale;
use App\Models\VerbaleTemplate;
use App\Services\AssembleaService;
use App\Services\SocioVariationService;
use App\Services\VerbalePdfService;
use App\Services\VerbaleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibroSociTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_admin_setup_is_required_for_default_credentials(): void
    {
        $admin = User::factory()->create([
            'name' => 'Amministratore',
            'username' => null,
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->get('/admin/login')
            ->assertRedirect('/setup');
    }

    public function test_initial_admin_setup_updates_default_admin_and_logs_in(): void
    {
        $admin = User::factory()->create([
            'name' => 'Amministratore',
            'username' => null,
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->post('/setup', [
            'username' => 'direzione',
            'password' => 'nuova-password',
            'password_confirmation' => 'nuova-password',
        ])->assertRedirect('/admin');

        $admin->refresh();

        $this->assertAuthenticatedAs($admin);
        $this->assertSame('direzione', $admin->name);
        $this->assertSame('direzione', $admin->username);
        $this->assertTrue(Hash::check('nuova-password', $admin->password));

        $this->get('/setup')
            ->assertRedirect('/admin');
    }

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
            ->get('/admin/assemblea')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/intestazione-documenti')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/modelli-verbali')
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

    public function test_verbale_template_can_be_saved_and_rendered_with_dynamic_fields(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $content = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Verbale per '],
                        ['type' => 'mergeTag', 'attrs' => ['id' => 'socio.nome_completo']],
                    ],
                ],
            ],
        ];

        Livewire::actingAs($admin)
            ->test(ModelliVerbali::class)
            ->set('data.tipo', 'ammissione')
            ->set('data.contenuto', $content)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(VerbaleTemplate::class, [
            'tipo' => 'ammissione',
        ]);

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

        $verbale = Verbale::create([
            'socio_id' => $socio->id,
            'tipo' => 'ammissione',
            'stato' => 'da_generare',
            'titolo' => 'Verbale test',
            'data_verbale' => '2026-05-25',
        ]);

        $html = app(VerbaleTemplateService::class)->render(
            $verbale,
            app(VerbalePdfService::class)->riepilogoSociale($verbale, $socio),
        );

        $this->assertStringContainsString('Verbale per', $html);
        $this->assertStringContainsString('Mario Rossi', $html);
    }

    public function test_ordinary_member_created_from_filament_form_saves_work_contract(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        Livewire::actingAs($admin)
            ->test(CreateSocio::class)
            ->set('data.nome', 'Mario')
            ->set('data.cognome', 'Rossi')
            ->set('data.codice_fiscale', 'RSSMRA80A01H501U')
            ->set('data.tipologia', 'ordinario')
            ->set('data.stato', 'attivo')
            ->set('data.data_ammissione', '2026-05-25')
            ->set('data.verbale_cda_path', UploadedFile::fake()->create('verbale-cda.pdf', 20, 'application/pdf'))
            ->set('data.capitale_versato', 100)
            ->set('data.contract_tipo_contratto', 'determinato')
            ->set('data.contract_data_inizio', '2026-06-01')
            ->set('data.contract_data_fine', '2026-12-31')
            ->set('data.contract_ore_settimanali', 30)
            ->call('create')
            ->assertHasNoErrors();

        $socio = Socio::query()
            ->where('codice_fiscale', 'RSSMRA80A01H501U')
            ->firstOrFail();

        $this->assertNotNull($socio->verbale_cda_path);
        Storage::disk('local')->assertExists($socio->verbale_cda_path);

        $this->assertDatabaseHas(SocioWorkContract::class, [
            'socio_id' => $socio->id,
            'tipo_contratto' => 'determinato',
            'data_inizio' => '2026-06-01 00:00:00',
            'data_fine' => '2026-12-31 00:00:00',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);
    }

    public function test_selected_members_can_be_bulk_updated_from_filament_table(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $firstSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'quota_sociale' => 25,
            'capitale_versato' => 100,
        ]);

        $secondSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Luigi',
            'cognome' => 'Verdi',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'quota_sociale' => 50,
            'capitale_versato' => 100,
        ]);

        Livewire::actingAs($admin)
            ->test(ListSocios::class)
            ->assertTableBulkActionExists('bulkEdit')
            ->callTableBulkAction('bulkEdit', [$firstSocio, $secondSocio], [
                'fields_to_update' => ['quota_sociale', 'capitale_versato'],
                'quota_sociale' => 150,
                'capitale_versato' => 250,
                'stato' => 'sospeso',
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('150.00', $firstSocio->refresh()->quota_sociale);
        $this->assertSame('150.00', $secondSocio->refresh()->quota_sociale);
        $this->assertSame('250.00', $firstSocio->capitale_versato);
        $this->assertSame('250.00', $secondSocio->capitale_versato);
        $this->assertSame('attivo', $firstSocio->stato);
        $this->assertSame('attivo', $secondSocio->stato);

        $this->assertDatabaseHas(SocioChange::class, [
            'socio_id' => $firstSocio->id,
            'field' => 'quota_sociale',
            'old_value' => '25.00',
            'new_value' => '150',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas(SocioChange::class, [
            'socio_id' => $secondSocio->id,
            'field' => 'quota_sociale',
            'old_value' => '50.00',
            'new_value' => '150',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas(SocioChange::class, [
            'socio_id' => $firstSocio->id,
            'field' => 'capitale_versato',
            'old_value' => '100.00',
            'new_value' => '250',
            'user_id' => $admin->id,
        ]);
    }

    public function test_assemblea_groups_variations_and_generates_summary_and_individual_verbales(): void
    {
        Storage::fake('local');

        $firstSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
            'is_cda_member' => true,
        ]);

        SocioWorkContract::create([
            'socio_id' => $firstSocio->id,
            'tipo_contratto' => 'determinato',
            'data_inizio' => '2026-06-01',
            'data_fine' => '2026-12-31',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);

        $secondSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Luigi',
            'cognome' => 'Verdi',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        SocioWorkContract::create([
            'socio_id' => $secondSocio->id,
            'tipo_contratto' => 'indeterminato',
            'data_inizio' => '2026-06-01',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);

        $assemblea = app(AssembleaService::class)->createWithVariations([
            'data_assemblea' => '2026-06-09',
            'titolo' => 'Assemblea del 09/06/2026',
            'variations' => [
                [
                    'socio_id' => $firstSocio->id,
                    'tipo' => 'proroga_contratto',
                    'data_effetto' => '2026-07-01',
                    'data_fine' => '2027-03-31',
                ],
                [
                    'socio_id' => $secondSocio->id,
                    'tipo' => 'cessazione_rapporto',
                    'data_effetto' => '2026-08-31',
                ],
            ],
        ]);

        $assemblea->refresh()->load('variations.verbale');

        $this->assertInstanceOf(Assemblea::class, $assemblea);
        $this->assertSame('2026-06-09', $assemblea->data_assemblea->format('Y-m-d'));
        $this->assertCount(2, $assemblea->variations);
        Storage::disk('local')->assertExists($assemblea->file_path);

        $firstVariation = $assemblea->variations->firstWhere('socio_id', $firstSocio->id);
        $secondVariation = $assemblea->variations->firstWhere('socio_id', $secondSocio->id);

        $this->assertSame('2026-06-09', $firstVariation->data_verbale->format('Y-m-d'));
        $this->assertSame('2026-07-01', $firstVariation->data_effetto->format('Y-m-d'));
        $this->assertSame('2026-06-09', $firstVariation->verbale->data_verbale->format('Y-m-d'));
        $this->assertSame('generato', $firstVariation->verbale->stato);
        Storage::disk('local')->assertExists($firstVariation->verbale->file_path);

        $this->assertSame('2026-08-31', $secondVariation->data_effetto->format('Y-m-d'));
        $this->assertSame('generato', $secondVariation->verbale->stato);
        $this->assertSame('recesso', $secondSocio->refresh()->stato);
        $this->assertSame('2026-08-31', $secondSocio->data_uscita->format('Y-m-d'));
    }

    public function test_assemblea_page_button_creates_assemblea(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $socio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        SocioWorkContract::create([
            'socio_id' => $socio->id,
            'tipo_contratto' => 'determinato',
            'data_inizio' => '2026-06-01',
            'data_fine' => '2026-12-31',
            'ore_settimanali' => 30,
            'stato' => 'attivo',
        ]);

        Livewire::actingAs($admin)
            ->test(AssembleaPage::class)
            ->set('data.data_assemblea', '2026-06-09')
            ->set('data.titolo', 'Assemblea test')
            ->set('data.variations', [
                [
                    'socio_id' => $socio->id,
                    'tipo' => 'proroga_contratto',
                    'data_effetto' => '2026-07-01',
                    'data_fine' => '2027-03-31',
                ],
            ])
            ->call('createAssemblea')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Assemblea::class, [
            'titolo' => 'Assemblea test',
        ]);
    }

    public function test_verbales_list_is_sorted_by_generation_date_by_default(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $firstSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $secondSocio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Luigi',
            'cognome' => 'Verdi',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $olderVerbale = Verbale::create([
            'socio_id' => $firstSocio->id,
            'tipo' => 'recesso',
            'stato' => 'generato',
            'titolo' => 'Verbale meno recente',
            'data_verbale' => '2026-06-01',
            'generato_il' => '2026-06-01 10:00:00',
        ]);

        $newerVerbale = Verbale::create([
            'socio_id' => $secondSocio->id,
            'tipo' => 'sospensione',
            'stato' => 'generato',
            'titolo' => 'Verbale piu recente',
            'data_verbale' => '2026-06-02',
            'generato_il' => '2026-06-02 10:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ListVerbales::class)
            ->assertCanSeeTableRecords([$newerVerbale, $olderVerbale], inOrder: true);
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
        Http::fake([
            'raw.githubusercontent.com/matteocontrini/comuni-json/*' => Http::response([
                [
                    'nome' => 'Roma',
                    'codice' => '058091',
                    'zona' => ['nome' => 'Centro'],
                    'regione' => ['nome' => 'Lazio'],
                    'provincia' => ['nome' => 'Roma'],
                    'sigla' => 'RM',
                    'codiceCatastale' => 'H501',
                ],
            ]),
        ]);

        $this->artisan('comuni:import', ['--source' => 'comuni-json'])
            ->assertSuccessful();

        $this->assertDatabaseHas('comunes', [
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'regione' => 'Lazio',
        ]);

        Http::assertSentCount(1);
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
            'tipologia' => 'ordinario',
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
            'tipologia' => 'ordinario',
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
            'tipologia' => 'ordinario',
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

    public function test_recesso_variation_verbale_pdf_is_saved(): void
    {
        Storage::fake('local');

        $socio = Socio::create([
            'tipologia' => 'ordinario',
            'nome' => 'Luigi',
            'cognome' => 'Verdi',
            'codice_fiscale' => 'VRDLGI80A01H501O',
            'data_ammissione' => '2026-05-25',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);

        $variation = app(SocioVariationService::class)->createAndApply([
            'socio_id' => $socio->id,
            'tipo' => 'recesso',
            'data_verbale' => '2026-09-20',
            'data_effetto' => '2026-09-30',
            'note' => 'Richiesta di recesso del socio.',
        ]);

        $verbale = app(VerbalePdfService::class)->generate($variation->verbale);

        $this->assertSame('recesso', $socio->refresh()->stato);
        $this->assertSame('generato', $verbale->stato);
        Storage::disk('local')->assertExists($verbale->file_path);
    }
}
