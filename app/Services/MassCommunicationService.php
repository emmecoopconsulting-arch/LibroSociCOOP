<?php

namespace App\Services;

use App\Mail\MassCommunicationMail;
use App\Models\MassCommunication;
use App\Models\Socio;
use App\Models\SocioDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MassCommunicationService
{
    public function __construct(private readonly OutboundMailService $mailService) {}

    public function recipientsQuery(array $filters): Builder
    {
        $statuses = array_values(array_filter((array) ($filters['statuses'] ?? [])));
        $types = array_values(array_filter((array) ($filters['types'] ?? [])));
        $recipientIds = array_values(array_filter(
            (array) ($filters['recipient_ids'] ?? []),
            fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false,
        ));

        return Socio::query()
            ->sociEffettivi()
            ->when($recipientIds !== [], fn (Builder $query) => $query->whereKey($recipientIds))
            ->when($recipientIds === [] && $statuses === [], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($recipientIds === [] && $statuses !== [], fn (Builder $query) => $query->whereIn('stato', $statuses))
            ->when($recipientIds === [] && $types !== [], fn (Builder $query) => $query->whereIn('tipologia', $types))
            ->when($recipientIds === [] && filled($filters['mansione'] ?? null), fn (Builder $query) => $query->where('mansione', $filters['mansione']))
            ->orderBy('cognome')
            ->orderBy('nome');
    }

    public function recipientCount(array $filters): int
    {
        return $this->recipientsQuery($filters)->count();
    }

    /** @return array{sent: int, failed: int, skipped: int} */
    public function send(MassCommunication $communication, bool $onlyFailed = false): array
    {
        $recipients = $this->recipientsQuery($communication->filters)->get();

        if ($recipients->contains(fn (Socio $socio): bool => filled($socio->email))) {
            $this->mailService->configure();
        }

        $communication->update([
            'status' => 'sending',
            'recipient_count' => $recipients->count(),
        ]);

        foreach ($recipients as $socio) {
            $delivery = $communication->deliveries()->firstOrNew(['socio_id' => $socio->id]);

            if ($onlyFailed && (! $delivery->exists || $delivery->status !== 'failed')) {
                continue;
            }
            if ($delivery->exists && $delivery->status === 'sent') {
                continue;
            }

            try {
                if (filled($communication->attachment_path)) {
                    $this->archiveAttachment($communication, $socio);
                }

                if (blank($socio->email)) {
                    $delivery->fill([
                        'email' => null,
                        'status' => 'skipped_no_email',
                        'error' => 'Comunicazione archiviata nel fascicolo; indirizzo email mancante.',
                    ])->save();

                    continue;
                }

                $delivery->fill(['email' => $socio->email, 'status' => 'pending', 'error' => null])->save();
                Mail::mailer('payroll')->to($socio->email)->send(new MassCommunicationMail($communication, $socio));
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Throwable $exception) {
                report($exception);
                $delivery->fill([
                    'email' => $socio->email,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ])->save();
            }
        }

        $sent = $communication->deliveries()->where('status', 'sent')->count();
        $failed = $communication->deliveries()->where('status', 'failed')->count();
        $skipped = $communication->deliveries()->where('status', 'skipped_no_email')->count();
        $communication->update([
            'status' => $failed > 0 ? 'partial' : 'sent',
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'sent_at' => $failed === 0 ? now() : null,
        ]);

        return compact('sent', 'failed', 'skipped');
    }

    private function archiveAttachment(MassCommunication $communication, Socio $socio): SocioDocument
    {
        $extension = pathinfo((string) $communication->attachment_name, PATHINFO_EXTENSION);
        $name = Str::slug(pathinfo((string) $communication->attachment_name, PATHINFO_FILENAME)) ?: 'allegato';
        $filename = "comunicazione-{$communication->id}-{$name}".($extension ? '.'.Str::lower($extension) : '');
        $relativePath = "documenti-soci/comunicazioni/{$socio->id}/{$filename}";

        Storage::disk('local')->makeDirectory(dirname($relativePath));
        Storage::disk('local')->copy($communication->attachment_path, $relativePath);

        return SocioDocument::query()->updateOrCreate(
            ['mass_communication_id' => $communication->id, 'socio_id' => $socio->id],
            [
                'tipo' => 'altro',
                'numero_documento' => "COM-{$communication->id}-{$socio->id}",
                'data_rilascio' => now()->toDateString(),
                'file_path' => $relativePath,
                'note' => $communication->attachment_description ?: $communication->subject,
            ],
        );
    }
}
