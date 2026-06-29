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
    public function __construct(private readonly LocalPayrollOcrService $ocrService) {}

    /**
     * @return array{sent: int, failed: int}
     */
    public function distribute(PayrollDistribution $distribution): array
    {
        $distribution->load(['pages.socio']);
        $unassigned = $distribution->pages->whereNull('socio_id')->count();

        if ($unassigned > 0) {
            throw new RuntimeException("Restano {$unassigned} pagine da associare.");
        }

        $missingEmail = $distribution->pages
            ->pluck('socio')
            ->filter()
            ->unique('id')
            ->filter(fn ($socio): bool => blank($socio->email));

        if ($missingEmail->isNotEmpty()) {
            throw new RuntimeException('Email mancante per: '.$missingEmail->pluck('nome_completo')->join(', '));
        }

        $this->configureMailer();
        $source = Storage::disk('local')->path($distribution->source_path);
        $distribution->update(['status' => 'sending', 'error' => null]);
        $sent = 0;
        $failed = 0;

        foreach ($distribution->pages->groupBy('socio_id') as $socioId => $pages) {
            $socio = $pages->first()->socio;
            $relativePath = "payroll/{$distribution->id}/deliveries/socio-{$socioId}.pdf";
            Storage::disk('local')->makeDirectory(dirname($relativePath));
            $absolutePath = Storage::disk('local')->path($relativePath);
            $this->ocrService->extractPages($source, $pages->pluck('page_number')->all(), $absolutePath);

            $delivery = $distribution->deliveries()->firstOrNew(['socio_id' => $socio->id]);

            if ($delivery->exists && $delivery->status === 'sent') {
                $sent++;

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
            'sent_at' => $failed === 0 ? now() : null,
        ]);

        return compact('sent', 'failed');
    }

    public function sendTest(string $recipient): void
    {
        $this->configureMailer();
        Mail::mailer('payroll')->raw(
            'Configurazione Amazon SES SMTP verificata correttamente.',
            fn ($message) => $message->to($recipient)->subject('Test invio buste paga'),
        );
    }

    private function configureMailer(): void
    {
        $host = AppSetting::string(AppSetting::SMTP_HOST);
        $username = AppSetting::string(AppSetting::SMTP_USERNAME);
        $password = AppSetting::smtpPassword();
        $from = AppSetting::string(AppSetting::SMTP_FROM_ADDRESS);

        if (blank($host) || blank($username) || blank($password) || blank($from)) {
            throw new RuntimeException('Configurazione SMTP incompleta. Compilarla in Impostazioni.');
        }

        config([
            'mail.mailers.payroll' => [
                'transport' => 'smtp',
                'scheme' => AppSetting::string(AppSetting::SMTP_SCHEME) ?: 'smtp',
                'host' => $host,
                'port' => AppSetting::int(AppSetting::SMTP_PORT),
                'username' => $username,
                'password' => $password,
                'timeout' => 30,
            ],
            'mail.from.address' => $from,
            'mail.from.name' => AppSetting::string(AppSetting::SMTP_FROM_NAME) ?: config('app.name'),
        ]);
        Mail::purge('payroll');
    }
}
