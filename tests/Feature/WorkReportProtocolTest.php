<?php

namespace Tests\Feature;

use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkReportProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_reports_receive_distinct_protocols_and_ignore_manual_input(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $first = $this->report(['protocollo' => 'MANUALE']);
        $second = $this->report();

        $this->assertSame('RI-2026-000001', $first->fresh()->protocollo);
        $this->assertSame('RI-2026-000002', $second->fresh()->protocollo);
    }

    public function test_protocol_survives_edits_and_numbers_are_not_reused_after_deletion(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $report = $this->report();
        $report->update(['protocollo' => 'ALTRO', 'data_intervento' => '2025-01-01']);

        $this->assertSame('RI-2026-000001', $report->fresh()->protocollo);
        $report->delete();
        $this->assertSame('RI-2026-000002', $this->report()->protocollo);
    }

    public function test_counter_restarts_in_registration_year_regardless_of_intervention_date(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31));
        $old = $this->report();
        $this->travelTo(now()->setDate(2027, 1, 1));
        $new = $this->report(['data_intervento' => '2026-12-20']);

        $this->assertSame('RI-2026-000001', $old->protocollo);
        $this->assertSame('RI-2027-000001', $new->protocollo);
    }

    public function test_existing_manual_protocols_are_preserved_and_sequence_continues(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 19));
        $id = DB::table('work_reports')->insertGetId([
            'protocollo' => 'RI-2026-010',
            'data_intervento' => '2026-01-01',
            'oggetto' => 'Rapportino preesistente',
            'rapportino_path' => 'rapporti-interventi/vecchio.pdf',
        ]);
        $this->assertSame('RI-2026-000011', $this->report()->protocollo);

        $old = WorkReport::findOrFail($id);
        $old->update(['note' => 'Aggiornato']);
        $this->assertSame('RI-2026-010', $old->fresh()->protocollo);
    }

    private function report(array $attributes = []): WorkReport
    {
        return WorkReport::create(array_replace([
            'data_intervento' => '2026-06-24',
            'oggetto' => 'Intervento',
            'rapportino_path' => 'rapporti-interventi/test.pdf',
        ], $attributes));
    }
}
