<?php

namespace Tests\Feature;

use App\Models\Socio;
use App\Models\SocioDocument;
use App\Models\WorkAbsence;
use App\Models\WorkOrder;
use App\Models\WorkOrderSite;
use App\Models\WorkReport;
use App\Models\WorkSite;
use App\Models\WorkVehicle;
use App\Services\WorkOrderPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_be_assigned_to_multiple_sites_when_times_do_not_overlap(): void
    {
        $socio = $this->socio('Mario', 'Rossi', 'RSSMRA80A01H501U');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $this->orderSite($order, 'Cantiere mattina', '08:00', '12:00', socioIds: [$socio->id]);
        $this->orderSite($order, 'Cantiere pomeriggio', '13:00', '17:00', socioIds: [$socio->id]);

        $this->assertDatabaseCount('work_order_sites', 2);
    }

    public function test_order_site_can_use_free_text_site_without_creating_archive_site(): void
    {
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $site = WorkOrderSite::create([
            'work_order_id' => $order->id,
            'work_site_name' => 'Cantiere temporaneo via Roma',
            'orario_inizio' => '08:00',
        ]);

        $this->assertNull($site->work_site_id);
        $this->assertSame('Cantiere temporaneo via Roma', $site->work_site_name);
        $this->assertDatabaseCount('work_sites', 0);
    }

    public function test_work_report_creates_archive_site_from_free_text_site(): void
    {
        $socio = $this->socio('Mario', 'Rossi', 'RSSMRA80A01H501U');

        $report = WorkReport::create([
            'protocollo' => 'RI-2026-001',
            'data_intervento' => '2026-06-24',
            'work_site_name' => 'Cantiere mobile cliente occasionale',
            'operator_hours' => [
                ['socio_id' => $socio->id, 'hours' => 3.5],
            ],
            'oggetto' => 'Pulizia straordinaria',
            'rapportino_path' => 'rapporti-interventi/rapportino-1.pdf',
        ]);

        $this->assertNotNull($report->work_site_id);
        $this->assertSame('Cantiere mobile cliente occasionale', $report->work_site_name);
        $this->assertDatabaseHas('work_sites', [
            'id' => $report->work_site_id,
            'nome' => 'Cantiere mobile cliente occasionale',
        ]);
    }

    public function test_free_text_site_matching_archive_label_keeps_archive_reference(): void
    {
        $archiveSite = WorkSite::create([
            'nome' => 'Cantiere archivio',
            'luogo' => 'Novi Ligure',
        ]);
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $site = WorkOrderSite::create([
            'work_order_id' => $order->id,
            'work_site_name' => 'Cantiere archivio - Novi Ligure',
            'orario_inizio' => '08:00',
        ]);

        $this->assertSame($archiveSite->id, $site->work_site_id);
        $this->assertDatabaseCount('work_sites', 1);
    }

    public function test_worker_cannot_be_assigned_to_overlapping_sites_in_same_order(): void
    {
        $socio = $this->socio('Luigi', 'Bianchi', 'BNCLGU80A01H501V');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $this->orderSite($order, 'Primo cantiere', '08:00', '12:00', socioIds: [$socio->id]);

        $this->expectException(ValidationException::class);

        $this->orderSite($order, 'Secondo cantiere', '11:00', '15:00', socioIds: [$socio->id]);
    }

    public function test_end_time_is_optional_for_order_site(): void
    {
        $socio = $this->socio('Giulia', 'Russo', 'RSSGLI80A41H501Z');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $site = $this->orderSite($order, 'Cantiere senza fine', '08:00', null, socioIds: [$socio->id]);

        $this->assertNull($site->orario_fine);
    }

    public function test_absent_worker_cannot_be_assigned_to_a_site(): void
    {
        $socio = $this->socio('Anna', 'Verdi', 'VRDNNA80A41H501E');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        WorkAbsence::create([
            'work_order_id' => $order->id,
            'socio_ids' => [$socio->id],
            'tipo' => 'ferie',
            'data_inizio' => '2026-06-24',
            'data_fine' => '2026-06-30',
        ]);

        $this->expectException(ValidationException::class);

        $this->orderSite($order, 'Cantiere', '08:00', '12:00', socioIds: [$socio->id]);
    }

    public function test_work_order_pdf_can_be_archived(): void
    {
        Storage::fake('local');

        $socio = $this->socio('Paolo', 'Neri', 'NREPLA80A01H501D');
        $vehicle = WorkVehicle::create([
            'nome' => 'Furgone 1',
            'tipo' => 'ditta',
            'targa' => 'AB123CD',
        ]);
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);
        $this->orderSite($order, 'Cantiere principale', '08:00', '12:00', $vehicle->id, [$socio->id]);

        $archived = app(WorkOrderPdfService::class)->generate($order, archive: true);

        $this->assertSame('archiviato', $archived->stato);
        $this->assertNotNull($archived->archiviato_il);
        Storage::disk('local')->assertExists($archived->pdf_path);
    }

    public function test_work_report_uses_manual_protocol_and_tracks_operators(): void
    {
        $socio = $this->socio('Paolo', 'Neri', 'NREPLA80A01H501D');
        $site = WorkSite::create([
            'nome' => 'Cantiere archivio',
            'luogo' => 'Novi Ligure',
        ]);

        $report = WorkReport::create([
            'protocollo' => 'RI-2026-010',
            'data_intervento' => '2026-06-24',
            'work_site_id' => $site->id,
            'operator_hours' => [
                ['socio_id' => $socio->id, 'hours' => 4],
            ],
            'oggetto' => 'Pulizia straordinaria',
            'descrizione_lavoro' => 'Intervento completato.',
            'rapportino_path' => 'rapporti-interventi/rapportino-1.pdf',
        ]);

        $this->assertSame('RI-2026-010', $report->protocollo);
        $this->assertSame([$socio->id], $report->socio_ids);
        $this->assertSame('4.00', $report->total_hours);
        $this->assertTrue($site->reports()->whereKey($report)->exists());
        $this->assertTrue($report->assignedSocios()->contains($socio));
    }

    public function test_work_report_sums_operator_hours(): void
    {
        $firstSocio = $this->socio('Paolo', 'Neri', 'NREPLA80A01H501D');
        $secondSocio = $this->socio('Carla', 'Gialli', 'GLLCRL80A41H501F');

        $report = WorkReport::create([
            'protocollo' => 'RI-2026-011',
            'data_intervento' => '2026-06-24',
            'work_site_name' => 'Cliente ore multiple',
            'operator_hours' => [
                ['socio_id' => $firstSocio->id, 'hours' => 2.5],
                ['socio_id' => $secondSocio->id, 'hours' => 3.75],
            ],
            'oggetto' => 'Pulizia ordinaria',
            'rapportino_path' => 'rapporti-interventi/rapportino-2.pdf',
        ]);

        $this->assertSame([$firstSocio->id, $secondSocio->id], $report->socio_ids);
        $this->assertSame('6.25', $report->total_hours);
        $this->assertDatabaseHas('work_sites', [
            'nome' => 'Cliente ore multiple',
        ]);
    }

    public function test_socio_can_have_multiple_archived_documents(): void
    {
        $socio = $this->socio('Carla', 'Gialli', 'GLLCRL80A41H501F');

        SocioDocument::create([
            'socio_id' => $socio->id,
            'tipo' => 'cie',
            'numero_documento' => 'CA12345AA',
            'data_scadenza' => '2030-01-31',
            'file_path' => 'documenti-soci/cie.pdf',
        ]);
        SocioDocument::create([
            'socio_id' => $socio->id,
            'tipo' => 'codice_fiscale',
            'file_path' => 'documenti-soci/codice-fiscale.pdf',
        ]);

        $this->assertCount(2, $socio->documents);
        $this->assertDatabaseHas('socio_documents', [
            'socio_id' => $socio->id,
            'tipo' => 'cie',
            'numero_documento' => 'CA12345AA',
        ]);
    }

    private function socio(string $nome, string $cognome, string $codiceFiscale): Socio
    {
        return Socio::create([
            'tipologia' => 'ordinario',
            'nome' => $nome,
            'cognome' => $cognome,
            'codice_fiscale' => $codiceFiscale,
            'data_ammissione' => '2026-01-01',
            'stato' => 'attivo',
            'capitale_versato' => 100,
        ]);
    }

    private function orderSite(WorkOrder $order, string $nome, string $inizio, ?string $fine, ?int $vehicleId = null, array $socioIds = []): WorkOrderSite
    {
        $site = WorkSite::create([
            'work_order_id' => $order->id,
            'nome' => $nome,
            'luogo' => 'Novi Ligure',
        ]);

        return WorkOrderSite::create([
            'work_order_id' => $order->id,
            'work_site_id' => $site->id,
            'work_vehicle_id' => $vehicleId,
            'socio_ids' => $socioIds,
            'orario_inizio' => $inizio,
            'orario_fine' => $fine,
        ]);
    }
}
