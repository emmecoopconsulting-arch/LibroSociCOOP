<?php

namespace App\Console\Commands;

use App\Services\VerbalePdfService;
use App\Services\VerbaleProgressionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('verbali:rigenera-progressivi {--dal=2024-12-31 : Data del saldo iniziale} {--soci-iniziali=60 : Soci ordinari al saldo iniziale} {--capitale-iniziale=6000 : Capitale sociale al saldo iniziale} {--apply : Rigenera i PDF, senza questa opzione esegue solo anteprima}')]
#[Description('Ricalcola i progressivi sociali e, con --apply, rigenera i verbali in ordine cronologico.')]
class RigeneraVerbaliProgressivi extends Command
{
    public function handle(VerbaleProgressionService $progression, VerbalePdfService $pdfService): int
    {
        $asOf = (string) $this->option('dal');
        $members = filter_var($this->option('soci-iniziali'), FILTER_VALIDATE_INT);
        $capital = filter_var($this->option('capitale-iniziale'), FILTER_VALIDATE_FLOAT);

        if ($members === false || $members < 0 || $capital === false || $capital < 0) {
            $this->error('Soci iniziali e capitale iniziale devono essere numeri non negativi.');

            return self::FAILURE;
        }

        try {
            $events = $progression->build($asOf, $members, $capital);
        } catch (\Throwable $exception) {
            $this->error('Impossibile ricostruire la sequenza: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($events->isEmpty()) {
            $this->warn('Non ci sono verbali collegati a eventi successivi al saldo iniziale.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Saldo al %s: %d soci ordinari, %s EUR', $asOf, $members, number_format($capital, 2, ',', '.')));
        $this->table(
            ['Data effetto', 'Socio', 'Evento', 'Soci dopo', 'Capitale dopo'],
            $events->map(fn (array $event): array => [
                $event['date']->format('d/m/Y'),
                $event['socio']->codice_socio,
                $event['type'],
                $event['members_after'],
                number_format($event['capital_after'], 2, ',', '.').' EUR',
            ])->all(),
        );

        foreach ($events->groupBy(fn (array $event): string => $event['date']->format('Y')) as $year => $yearEvents) {
            $last = $yearEvents->last();
            $this->line(sprintf(
                'Saldo eventi %s: %d soci ordinari, %s EUR',
                $year,
                $last['members_after'],
                number_format($last['capital_after'], 2, ',', '.'),
            ));
        }

        $checkpoint2025 = $events
            ->filter(fn (array $event): bool => $event['date']->toDateString() <= '2025-12-31')
            ->last();

        if ($checkpoint2025) {
            $this->line(sprintf(
                'Controllo al 31/12/2025: %d soci (atteso: 73), capitale %s EUR',
                $checkpoint2025['members_after'],
                number_format($checkpoint2025['capital_after'], 2, ',', '.'),
            ));

            if ($checkpoint2025['members_after'] !== 73 && $this->option('apply')) {
                $this->error('Il conteggio non coincide con il dato ufficiale di 73 soci. Correggere la sequenza prima di applicare.');

                return self::FAILURE;
            }
        }

        if (! $this->option('apply')) {
            $this->comment('Anteprima soltanto. Aggiungi --apply per rigenerare i PDF e aggiornare l’archivio configurato.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Rigenerare i PDF elencati?')) {
            $this->warn('Operazione annullata.');

            return self::SUCCESS;
        }

        $generated = 0;

        foreach ($events as $event) {
            $pdfService->generateProgressive($event['verbale'], $event['summary'], $event['variation']);
            $generated++;
        }

        $this->info("Verbali rigenerati: {$generated}");

        return self::SUCCESS;
    }
}
