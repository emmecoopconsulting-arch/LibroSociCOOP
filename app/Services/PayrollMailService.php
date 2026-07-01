<?php

namespace App\Services;

use App\Mail\PayrollMail;
use App\Models\AppSetting;
use App\Models\PayrollDistribution;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PayrollMailService
{
    public function __construct(private readonly PayrollDocumentService $documentService) {}

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function distribute(PayrollDistribution $distribution): array
    {
        $distribution->load(['pages.socio']);
        $unassigned = $distribution->pages->whereNull('socio_id')->count();

        if ($unassigned > 0) {
            throw new RuntimeException("Restano {$unassigned} pagine da associare.");
        }

        $documents = $this->documentService->sync($distribution);
        $hasEmailRecipients = $distribution->pages
            ->pluck('socio')
            ->filter(fn ($socio): bool => filled($socio?->email))
            ->isNotEmpty();

        if ($hasEmailRecipients) {
            $this->configureMailer();
        }

        $distribution->update(['status' => 'sending', 'error' => null]);
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($distribution->pages->groupBy('socio_id') as $socioId => $pages) {
            $socio = $pages->first()->socio;
            $relativePath = $documents[(int) $socioId]->file_path;
            $absolutePath = Storage::disk('local')->path($relativePath);

            $delivery = $distribution->deliveries()->firstOrNew(['socio_id' => $socio->id]);

            if ($delivery->exists && $delivery->status === 'sent') {
                $sent++;

                continue;
            }

            if (blank($socio->email)) {
                $delivery->fill([
                    'email' => '',
                    'attachment_path' => $relativePath,
                    'status' => 'skipped_no_email',
                    'error' => 'Documento archiviato nello storico del socio; email non presente.',
                ])->save();
                $skipped++;

                continue;
            }

            $delivery->fill([
                'email' => $socio->email,
                'attachment_path' => $relativePath,
                'status' => 'pending',
                'error' => null,
            ])->save();

            try {
                Mail::mailer('payroll')->to($socio->email)->send(
                    new PayrollMail($socio, $distribution->period ?: 'indicato', $absolutePath),
                );
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $delivery->update(['status' => 'failed', 'error' => $exception->getMessage()]);
                $failed++;
            }
        }

        $distribution->update([
            'status' => $failed > 0 ? 'partial' : 'sent',
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'sent_at' => $failed === 0 ? now() : null,
        ]);

        return compact('sent', 'failed', 'skipped');
    }

    public function sendTest(string $recipient): void
    {
        $this->configureMailer();
        Mail::mailer('payroll')->raw(
            'Configurazione SMTP verificata correttamente.',
            fn ($message) => $message->to($recipient)->subject('Test invio buste paga'),
        );
    }

    private function configureMailer(): void
    {
        $traditional = AppSetting::string(AppSetting::MAIL_PROVIDER) === 'traditional';
        $host = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_HOST : AppSetting::SMTP_HOST);
        $username = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_USERNAME : AppSetting::SMTP_USERNAME);
        $password = $traditional ? AppSetting::traditionalSmtpPassword() : AppSetting::smtpPassword();
        $from = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_FROM_ADDRESS : AppSetting::SMTP_FROM_ADDRESS);
        $scheme = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_SCHEME : AppSetting::SMTP_SCHEME) ?: 'smtp';
        $port = AppSetting::int($traditional ? AppSetting::TRADITIONAL_SMTP_PORT : AppSetting::SMTP_PORT);
        $fromName = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_FROM_NAME : AppSetting::SMTP_FROM_NAME);

        if (blank($host) || blank($username) || blank($password) || blank($from)) {
            throw new RuntimeException('Configurazione del provider SMTP attivo incompleta. Compilarla in Impostazioni.');
        }

        config([
            'mail.mailers.payroll' => [
                'transport' => 'smtp',
                'scheme' => $scheme === 'none' ? 'smtp' : $scheme,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'timeout' => 30,
                'auto_tls' => $scheme !== 'none',
            ],
            'mail.from.address' => $from,
            'mail.from.name' => $fromName ?: config('app.name'),
        ]);
        Mail::purge('payroll');
    }
}
