<?php

namespace Tests\Feature;

use App\Filament\Widgets\SociOverview;
use App\Models\Socio;
use App\Models\SocioVariation;
use App\Services\SocioExcelImportService;
use App\Services\SocioVariationService;
use App\Services\VerbalePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecessoCapitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_recesso_zeros_both_amounts_and_preserves_the_audit(): void
    {
        $socio = $this->member();

        $socio->update(['stato' => 'recesso', 'data_uscita' => '2026-10-16']);

        $this->assertSame('0.00', $socio->refresh()->quota_sociale);
        $this->assertSame('0.00', $socio->capitale_versato);
        $this->assertSame('2026-10-16', $socio->data_uscita->toDateString());
        $this->assertDatabaseHas('socio_changes', [
            'socio_id' => $socio->id,
            'field' => 'capitale_versato',
            'old_value' => '80.25',
            'new_value' => '0.00',
        ]);

        $socio->update(['quota_sociale' => 900, 'capitale_versato' => 900]);
        $this->assertSame('0.00', $socio->refresh()->quota_sociale);
        $this->assertSame('0.00', $socio->capitale_versato);
    }

    public function test_new_withdrawn_members_cannot_have_capital(): void
    {
        $socio = $this->member(['stato' => 'recesso']);

        $this->assertSame('0.00', $socio->refresh()->quota_sociale);
        $this->assertSame('0.00', $socio->capitale_versato);
    }

    public function test_other_member_statuses_keep_their_amounts(): void
    {
        foreach (['attivo', 'sospeso', 'escluso'] as $status) {
            $socio = $this->member(['stato' => $status]);
            $socio->update(['note' => 'Controllo']);

            $this->assertSame('100.50', $socio->refresh()->quota_sociale);
            $this->assertSame('80.25', $socio->capitale_versato);
        }
    }

    public function test_guided_recesso_and_work_termination_preserve_financial_snapshots(): void
    {
        foreach (['recesso', 'cessazione_rapporto'] as $type) {
            $socio = $this->member();
            $variation = app(SocioVariationService::class)->createAndApply([
                'socio_id' => $socio->id,
                'tipo' => $type,
                'data_verbale' => '2026-10-16',
                'data_effetto' => '2026-10-16',
            ]);

            $this->assertSame('recesso', $socio->refresh()->stato);
            $this->assertSame('0.00', $socio->quota_sociale);
            $this->assertSame('0.00', $socio->capitale_versato);
            $this->assertSame('100.50', $variation->snapshot_precedente['socio']['quota_sociale']);
            $this->assertSame('80.25', $variation->snapshot_precedente['socio']['capitale_versato']);
            $this->assertSame('0.00', $variation->snapshot_successivo['socio']['capitale_versato']);

            $summary = app(VerbalePdfService::class)->riepilogoSociale($variation->verbale, $socio);
            $this->assertSame(80.25, $summary['capitale_sociale_uscito']);
            $this->assertSame(0.0, $summary['capitale_sociale_complessivo']);
        }
    }

    public function test_import_cannot_reintroduce_capital_for_withdrawn_members(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recesso.csv', "Nome,Cognome,Codice fiscale,Stato,Quota sociale,Capitale versato\nMario,Rossi,RSSMRA80A01H501U,recesso,100.50,80.25\n");
        $service = app(SocioExcelImportService::class);
        $path = Storage::disk('local')->path('recesso.csv');
        $mapping = $service->guessedMapping($path);

        $this->assertSame(1, $service->import($path, $mapping)['created']);
        $this->assertSame(1, $service->import($path, $mapping, updateExisting: true)['updated']);
        $socio = Socio::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->firstOrFail();
        $this->assertSame('0.00', $socio->quota_sociale);
        $this->assertSame('0.00', $socio->capitale_versato);
    }

    public function test_cleanup_is_selective_audited_and_idempotent(): void
    {
        $active = $this->member();
        $suspended = $this->member(['stato' => 'sospeso']);
        $excluded = $this->member(['stato' => 'escluso']);
        $withdrawn = $this->legacyRecesso();
        $deleted = $this->legacyRecesso(['deleted_at' => now()]);
        $quotaOnly = $this->legacyRecesso(['capitale_versato' => 0]);
        $capitalOnly = $this->legacyRecesso(['quota_sociale' => 0]);
        $zero = $this->member(['stato' => 'recesso']);
        $unchanged = DB::table('socios')->whereIn('id', [$active->id, $suspended->id, $excluded->id, $zero->id])->get()->toJson();

        $migration = require database_path('migrations/2026_10_16_000001_zero_recesso_share_capital.php');
        $migration->up();

        foreach ([$withdrawn, $deleted, $quotaOnly, $capitalOnly] as $socio) {
            $row = DB::table('socios')->find($socio->id);
            $this->assertEquals(0, $row->quota_sociale);
            $this->assertEquals(0, $row->capitale_versato);
            $this->assertSame('2026-09-30', $row->data_uscita);
            $this->assertSame('Dati storici', $row->note);
        }

        $this->assertTrue($deleted->refresh()->trashed());
        $this->assertSame($unchanged, DB::table('socios')->whereIn('id', [$active->id, $suspended->id, $excluded->id, $zero->id])->get()->toJson());
        $this->assertSame(6, DB::table('socio_changes')->count());
        $this->assertSame(4, DB::table('activity_log')->where('event', 'bonifica_recesso')->count());
        $this->assertSame(100.50, (float) DB::table('socio_changes')
            ->where('socio_id', $withdrawn->id)->where('field', 'quota_sociale')->value('old_value'));
        $audit = DB::table('activity_log')->where('event', 'bonifica_recesso')->where('subject_id', $withdrawn->id)->first();
        $this->assertEquals(80.25, json_decode($audit->attribute_changes, true)['old']['capitale_versato']);

        $migration->up();
        $this->assertSame(6, DB::table('socio_changes')->count());
        $this->assertSame(4, DB::table('activity_log')->where('event', 'bonifica_recesso')->count());
    }

    public function test_cleanup_processes_members_beyond_the_first_batch(): void
    {
        for ($index = 0; $index < 205; $index++) {
            $this->legacyRecesso();
        }

        $migration = require database_path('migrations/2026_10_16_000001_zero_recesso_share_capital.php');
        $migration->up();

        $this->assertSame(0, DB::table('socios')->where('stato', 'recesso')
            ->where(fn ($query) => $query->where('quota_sociale', '!=', 0)->orWhere('capitale_versato', '!=', 0))->count());
        $this->assertSame(410, DB::table('socio_changes')->count());
        $this->assertSame(205, DB::table('activity_log')->where('event', 'bonifica_recesso')->count());
    }

    public function test_capital_overview_excludes_legacy_recesso_even_before_cleanup(): void
    {
        $this->member();
        $this->member(['stato' => 'sospeso']);
        $this->legacyRecesso();
        $this->member(['tipologia' => Socio::TIPOLOGIA_MEMBRO_CDA]);

        $this->assertSame(160.50, (float) Socio::query()->conCapitaleSociale()->sum('capitale_versato'));
        $widget = new class extends SociOverview
        {
            public function stats(): array
            {
                return $this->getStats();
            }
        };
        $capital = collect($widget->stats())->first(fn ($stat) => $stat->getLabel() === 'Capitale sociale');
        $this->assertSame('160,50 EUR', $capital->getValue());
    }

    public function test_legacy_exit_summary_uses_the_audited_amount_after_cleanup(): void
    {
        $socio = $this->legacyRecesso();
        $verbale = $socio->verbales()->create([
            'tipo' => 'recesso', 'stato' => 'da_generare', 'titolo' => 'Recesso storico', 'data_verbale' => '2026-09-30',
        ]);
        $variation = SocioVariation::create([
            'socio_id' => $socio->id, 'verbale_id' => $verbale->id, 'tipo' => 'recesso', 'stato' => 'applicata',
            'data_verbale' => '2026-09-30', 'data_effetto' => '2026-09-30',
            'snapshot_precedente' => ['socio' => ['stato' => 'attivo']],
            'snapshot_successivo' => ['socio' => ['stato' => 'recesso']],
        ]);
        $before = $variation->snapshot_precedente;
        $migration = require database_path('migrations/2026_10_16_000001_zero_recesso_share_capital.php');
        $migration->up();

        $summary = app(VerbalePdfService::class)->riepilogoSociale($verbale, $socio->refresh());
        $this->assertSame(80.25, $summary['capitale_sociale_uscito']);
        $this->assertSame(0.0, $summary['capitale_sociale_complessivo']);
        $this->assertSame($before, $variation->refresh()->snapshot_precedente);
    }

    private function legacyRecesso(array $overrides = []): Socio
    {
        $socio = $this->member();
        // Simulate records written by the old application, bypassing new hooks.
        DB::table('socios')->where('id', $socio->id)->update([
            'stato' => 'recesso', 'data_uscita' => '2026-09-30', 'note' => 'Dati storici', ...$overrides,
        ]);

        return $socio->refresh();
    }

    private function member(array $overrides = []): Socio
    {
        return Socio::create([
            'tipologia' => 'ordinario', 'nome' => 'Mario', 'cognome' => 'Rossi',
            'codice_fiscale' => sprintf('TEST%012d', Socio::withTrashed()->count() + 1),
            'data_ammissione' => '2026-01-01', 'stato' => 'attivo',
            'quota_sociale' => '100.50', 'capitale_versato' => '80.25', ...$overrides,
        ]);
    }
}
