<?php

namespace Tests\Feature;

use App\Models\Socio;
use App\Models\WorkAbsence;
use App\Models\WorkOrder;
use App\Models\WorkSite;
use App\Models\WorkSiteAssignment;
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

        $morning = $this->site($order, 'Cantiere mattina', '08:00', '12:00');
        $afternoon = $this->site($order, 'Cantiere pomeriggio', '13:00', '17:00');

        WorkSiteAssignment::create([
            'work_site_id' => $morning->id,
            'socio_id' => $socio->id,
        ]);
        WorkSiteAssignment::create([
            'work_site_id' => $afternoon->id,
            'socio_id' => $socio->id,
        ]);

        $this->assertDatabaseCount('work_site_assignments', 2);
    }

    public function test_worker_cannot_be_assigned_to_overlapping_sites_in_same_order(): void
    {
        $socio = $this->socio('Luigi', 'Bianchi', 'BNCLGU80A01H501V');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);

        $first = $this->site($order, 'Primo cantiere', '08:00', '12:00');
        $second = $this->site($order, 'Secondo cantiere', '11:00', '15:00');

        WorkSiteAssignment::create([
            'work_site_id' => $first->id,
            'socio_id' => $socio->id,
        ]);

        $this->expectException(ValidationException::class);

        WorkSiteAssignment::create([
            'work_site_id' => $second->id,
            'socio_id' => $socio->id,
        ]);
    }

    public function test_absent_worker_cannot_be_assigned_to_a_site(): void
    {
        $socio = $this->socio('Anna', 'Verdi', 'VRDNNA80A41H501E');
        $order = WorkOrder::create([
            'data_servizio' => '2026-06-24',
            'titolo' => 'Ordine di servizio del 24/06/2026',
        ]);
        $site = $this->site($order, 'Cantiere', '08:00', '12:00');

        WorkAbsence::create([
            'work_order_id' => $order->id,
            'socio_id' => $socio->id,
            'tipo' => 'ferie',
        ]);

        $this->expectException(ValidationException::class);

        WorkSiteAssignment::create([
            'work_site_id' => $site->id,
            'socio_id' => $socio->id,
        ]);
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
        $site = $this->site($order, 'Cantiere principale', '08:00', '12:00', $vehicle->id);

        WorkSiteAssignment::create([
            'work_site_id' => $site->id,
            'socio_id' => $socio->id,
        ]);

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

    private function site(WorkOrder $order, string $nome, string $inizio, string $fine, ?int $vehicleId = null): WorkSite
    {
        return WorkSite::create([
            'work_order_id' => $order->id,
            'work_vehicle_id' => $vehicleId,
            'nome' => $nome,
            'luogo' => 'Novi Ligure',
            'orario_inizio' => $inizio,
            'orario_fine' => $fine,
        ]);
    }
}
