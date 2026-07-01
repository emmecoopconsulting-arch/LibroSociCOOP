<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\PayrollDistribution;
use App\Models\Socio;
use App\Models\SocioDocument;
use App\Services\LocalPayrollOcrService;
use App\Services\PayrollMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_traditional_smtp_can_be_selected_without_overwriting_ses(): void
    {
        AppSetting::setValue(AppSetting::MAIL_PROVIDER, 'traditional');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_HOST, 'smtp.example.test');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_PORT, 465);
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_SCHEME, 'smtps');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_USERNAME, 'user@example.test');
        AppSetting::setTraditionalSmtpPassword('traditional-secret');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_FROM_ADDRESS, 'paghe@example.test');
        AppSetting::setValue(AppSetting::SMTP_HOST, 'email-smtp.eu-north-1.amazonaws.com');
        Mail::fake();

        app(PayrollMailService::class)->sendTest('admin@example.test');

        $this->assertSame('smtp.example.test', config('mail.mailers.payroll.host'));
        $this->assertSame(465, config('mail.mailers.payroll.port'));
        $this->assertSame('smtps', config('mail.mailers.payroll.scheme'));
        $this->assertSame('paghe@example.test', config('mail.from.address'));
        $this->assertSame('email-smtp.eu-north-1.amazonaws.com', AppSetting::string(AppSetting::SMTP_HOST));
        $this->assertNotSame(
            'traditional-secret',
            AppSetting::query()->where('key', AppSetting::TRADITIONAL_SMTP_PASSWORD)->firstOrFail()->value,
        );
    }

    public function test_assigned_payroll_is_archived_for_a_socio_without_email(): void
    {
        Storage::fake('local');
        $socio = Socio::create($this->socioData(['email' => null]));
        $source = Storage::disk('local')->path('payroll/sources/buste.pdf');
        Storage::disk('local')->makeDirectory('payroll/sources');
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Text(10, 10, 'Busta paga Mario Rossi');
        $pdf->Output('F', $source);

        $distribution = PayrollDistribution::create([
            'original_name' => 'buste.pdf',
            'source_path' => 'payroll/sources/buste.pdf',
            'period' => 'Giugno 2026',
            'status' => 'review',
        ]);
        $distribution->pages()->create([
            'socio_id' => $socio->id,
            'page_number' => 1,
            'page_path' => 'payroll/1/pages/page-1.pdf',
            'match_reason' => 'Confermato manualmente',
        ]);

        $result = app(PayrollMailService::class)->distribute($distribution);

        $this->assertSame(['sent' => 0, 'failed' => 0, 'skipped' => 1], $result);
        $document = SocioDocument::query()->whereBelongsTo($socio)->firstOrFail();
        $this->assertSame('busta_paga', $document->tipo);
        $this->assertSame('Giugno 2026', $document->periodo_riferimento);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertDatabaseHas('payroll_deliveries', [
            'payroll_distribution_id' => $distribution->id,
            'socio_id' => $socio->id,
            'status' => 'skipped_no_email',
        ]);
        $this->assertDatabaseHas('payroll_distributions', [
            'id' => $distribution->id,
            'skipped_count' => 1,
        ]);
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
