<?php

namespace App\Console\Commands;

use App\Services\ApplicationUpdateService;
use Illuminate\Console\Command;
use Throwable;

class UpdateApplication extends Command
{
    protected $signature = 'app:update {--check : Controlla soltanto se ci sono aggiornamenti}';

    protected $description = 'Aggiorna il progetto da GitHub e applica dipendenze, build e migrazioni.';

    public function handle(ApplicationUpdateService $service): int
    {
        try {
            $check = $service->check();

            $this->line("Branch: {$check['branch']}");
            $this->line("Versione locale: {$check['current']}");
            $this->line("Versione GitHub: {$check['remote']}");
            $this->line($check['message']);

            if ($check['dirty']) {
                $this->warn('Sono presenti modifiche locali non salvate. Commit o pulizia richiesti prima di aggiornare.');

                return self::FAILURE;
            }

            if ($this->option('check') || ! $check['available']) {
                return self::SUCCESS;
            }

            foreach ($service->update() as $step) {
                $this->info('Eseguito: '.$step['command']);
            }

            $this->info('Aggiornamento completato.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
