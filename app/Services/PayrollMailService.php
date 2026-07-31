<?php

namespace App\Services;

use App\Mail\PayrollMail;
use App\Models\PayrollDistribution;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PayrollMailService
{
    public function __construct(
        private readonly PayrollDocumentService $documentService,
        private readonly OutboundMailService $outboundMailService,
    ) {}

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function distribute(PayrollDistribution $distribution, bool $onlyFailed = false): array
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
            $this->outboundMailService->configure();
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

            if ($onlyFailed && (! $delivery->exists || $delivery->status !== 'failed')) {
                continue;
            }

            if ($delivery->exists && $delivery->status === 'sent') {
                continue;
            }

            if (blank($socio->email)) {
                $delivery->fill([
                    'email' => '',
                    'attachment_path' => $relativePath,
                    'status' => 'skipped_no_email',
                    'error' => 'Documento archiviato nello storico del socio; email non presente.',
                ])->save();

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
                    new PayrollMail(
                        $socio,
                        $distribution->period ?: 'indicato',
                        $absolutePath,
                        $distribution->additional_message,
                    ),
                );
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Throwable $exception) {
                report($exception);
                $delivery->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            }
        }

        $sent = $distribution->deliveries()->where('status', 'sent')->count();
        $failed = $distribution->deliveries()->where('status', 'failed')->count();
        $skipped = $distribution->deliveries()->where('status', 'skipped_no_email')->count();

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
        $this->outboundMailService->configure();
        Mail::mailer('payroll')->raw(
            'Configurazione SMTP verificata correttamente.',
            fn ($message) => $message->to($recipient)->subject('Test invio buste paga'),
        );
    }
}
