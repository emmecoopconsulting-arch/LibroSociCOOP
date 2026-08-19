<?php

namespace Tests\Feature;

use App\Mail\MassCommunicationMail;
use App\Models\AppSetting;
use App\Models\MassCommunication;
use App\Models\Socio;
use App\Models\SocioDocument;
use App\Models\User;
use App\Services\MassCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MassCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mass_communication_page_is_available_to_an_administrator(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('amministratore');
        $admin->assignRole('amministratore');

        $this->actingAs($admin)->get('/admin/comunicazioni-massive')->assertOk();
    }

    public function test_filtered_communication_is_sent_and_attachment_is_archived_for_each_recipient(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->configureMail();

        $withEmail = Socio::create($this->socioData(['email' => 'mario@example.test']));
        $withoutEmail = Socio::create($this->socioData([
            'nome' => 'Luigi',
            'codice_fiscale' => 'BNCLGU80A01F205Y',
            'email' => null,
        ]));
        Socio::create($this->socioData([
            'nome' => 'Anna',
            'codice_fiscale' => 'VRDNNA80A41F205Z',
            'email' => 'anna@example.test',
            'stato' => 'recesso',
        ]));
        Storage::disk('local')->put('comunicazioni/allegati/avviso.pdf', 'contenuto-pdf');

        $communication = MassCommunication::create([
            'subject' => 'Nuovo regolamento',
            'body' => "Leggere con attenzione.\nGrazie.",
            'attachment_path' => 'comunicazioni/allegati/avviso.pdf',
            'attachment_name' => 'Regolamento.pdf',
            'attachment_description' => 'Regolamento interno 2026',
            'filters' => ['statuses' => ['attivo'], 'types' => [], 'mansione' => null],
        ]);

        $result = app(MassCommunicationService::class)->send($communication);

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 1], $result);
        Mail::assertSent(MassCommunicationMail::class, 1);
        Mail::assertSent(MassCommunicationMail::class, fn (MassCommunicationMail $mail): bool => $mail->socio->is($withEmail));
        $this->assertSame(2, SocioDocument::query()->where('mass_communication_id', $communication->id)->count());
        $this->assertDatabaseHas('mass_communication_deliveries', [
            'mass_communication_id' => $communication->id,
            'socio_id' => $withoutEmail->id,
            'status' => 'skipped_no_email',
        ]);

        foreach (SocioDocument::query()->where('mass_communication_id', $communication->id)->get() as $document) {
            $this->assertSame('Regolamento interno 2026', $document->note);
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    public function test_specific_recipients_replace_the_standard_filters(): void
    {
        Mail::fake();
        $this->configureMail();

        $selected = Socio::create($this->socioData([
            'email' => 'selected@example.test',
            'stato' => 'recesso',
        ]));
        $notSelected = Socio::create($this->socioData([
            'nome' => 'Luigi',
            'codice_fiscale' => 'BNCLGU80A01F205Y',
            'email' => 'luigi@example.test',
        ]));

        $communication = MassCommunication::create([
            'subject' => 'Comunicazione personale',
            'body' => 'Messaggio riservato.',
            'filters' => [
                'statuses' => ['attivo'],
                'types' => [],
                'mansione' => null,
                'recipient_ids' => [$selected->id],
            ],
        ]);

        $result = app(MassCommunicationService::class)->send($communication);

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 0], $result);
        Mail::assertSent(MassCommunicationMail::class, 1);
        Mail::assertSent(MassCommunicationMail::class, fn (MassCommunicationMail $mail): bool => $mail->socio->is($selected));
        $this->assertDatabaseMissing('mass_communication_deliveries', [
            'mass_communication_id' => $communication->id,
            'socio_id' => $notSelected->id,
        ]);
    }

    private function configureMail(): void
    {
        AppSetting::setValue(AppSetting::MAIL_PROVIDER, 'traditional');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_HOST, 'smtp.example.test');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_PORT, 587);
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_SCHEME, 'smtp');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_USERNAME, 'user');
        AppSetting::setTraditionalSmtpPassword('secret');
        AppSetting::setValue(AppSetting::TRADITIONAL_SMTP_FROM_ADDRESS, 'info@example.test');
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
