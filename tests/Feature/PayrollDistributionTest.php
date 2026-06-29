<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\PayrollDistribution;
use App\Models\Socio;
use App\Services\LocalPayrollOcrService;
use App\Services\PayrollMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PayrollDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_matching_prioritizes_an_exact_tax_code(): void
    {
        $correct = Socio::create($this->socioData([
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01F205X',
            'email' => 'mario@example.test',
        ]));
        Socio::create($this->socioData([
            'nome' => 'Mario',
            'cognome' => 'Bianchi',
            'codice_fiscale' => 'BNCMRA80A01F205Y',
            'email' => 'bianchi@example.test',
        ]));

        $match = app(LocalPayrollOcrService::class)->matchSocio(
            'Cedolino dipendente RSSMRA80A01F205X Mario Rossi',
            Socio::all(),
        );

        $this->assertSame($correct->id, $match['socio_id']);
        $this->assertSame(100, $match['confidence']);
        $this->assertSame('Codice fiscale', $match['reason']);
    }

    public function test_ambiguous_name_match_is_not_assigned(): void
    {
        Socio::create($this->socioData(['codice_fiscale' => 'RSSMRA80A01F205X']));
        Socio::create($this->socioData(['codice_fiscale' => 'RSSMRA81A01F205Y']));

        $match = app(LocalPayrollOcrService::class)->matchSocio('Mario Rossi', Socio::all());

        $this->assertNull($match['socio_id']);
        $this->assertSame(0, $match['confidence']);
    }

    public function test_pdf_pages_can_be_extracted_into_a_private_attachment(): void
    {
        Storage::fake('local');
        $source = Storage::disk('local')->path('source.pdf');
        $destination = Storage::disk('local')->path('page.pdf');
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(10, 10, 'Pagina uno');
        $pdf->AddPage();
        $pdf->Text(10, 10, 'Pagina due');
        $pdf->Output('F', $source);

        app(LocalPayrollOcrService::class)->extractPages($source, [2], $destination);

        $result = new Fpdi;
        $this->assertSame(1, $result->setSourceFile($destination));
    }

    public function test_delivery_is_blocked_while_a_page_is_unassigned(): void
    {
        $distribution = PayrollDistribution::create([
            'original_name' => 'buste.pdf',
            'source_path' => 'payroll/sources/buste.pdf',
            'period' => 'Giugno 2026',
            'status' => 'review',
        ]);
        $distribution->pages()->create([
            'page_number' => 1,
            'page_path' => 'payroll/1/pages/page-1.pdf',
            'match_reason' => 'Da associare',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Restano 1 pagine da associare.');

        app(PayrollMailService::class)->distribute($distribution);
    }

    public function test_smtp_password_is_encrypted_at_rest(): void
    {
        AppSetting::setSmtpPassword('segreto-smtp');

        $this->assertSame('segreto-smtp', AppSetting::smtpPassword());
        $this->assertNotSame(
            'segreto-smtp',
            AppSetting::query()->where('key', AppSetting::SMTP_PASSWORD)->firstOrFail()->value,
        );
    }

    private function socioData(array $overrides = []): array
    {
        return array_merge([
            'tipologia' => 'ordinario',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'codice_fiscale' => 'RSSMRA80A01F205X',
            'data_ammissione' => now()->toDateString(),
            'stato' => 'attivo',
            'quota_sociale' => 0,
            'capitale_versato' => 0,
        ], $overrides);
    }
}
