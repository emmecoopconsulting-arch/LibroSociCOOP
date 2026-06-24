<?php

namespace Tests\Feature;

use App\Models\Socio;
use App\Models\WorkAbsence;
use App\Models\WorkOrder;
use App\Models\WorkOrderSite;
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
