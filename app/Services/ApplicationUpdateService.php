<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

class ApplicationUpdateService
{
    /**
     * @return array{available: bool, current: string, remote: string, behind: int, branch: string, dirty: bool, can_update: bool, update_command: string|null, message: string}
     */
    public function check(): array
    {
        if ($this->isDocker()) {
            return $this->checkDockerImage();
        }

        if (! $this->isGitRepository()) {
            return [
                'available' => false,
                'current' => '-',
                'remote' => '-',
                'behind' => 0,
                'branch' => '-',
                'dirty' => false,
                'can_update' => false,
                'update_command' => null,
                'message' => 'Aggiornamento automatico non disponibile: questa cartella non è collegata a GitHub.',
            ];
        }

        $current = $this->run(['git', 'rev-parse', '--short', 'HEAD']);
        $branch = $this->run(['git', 'branch', '--show-current']);
        $dirty = filled($this->run(['git', 'status', '--porcelain']));

        $this->run(['git', 'fetch', '--quiet']);

        $upstream = $this->upstream();
        $remote = $this->run(['git', 'rev-parse', '--short', $upstream]);
        $behind = (int) $this->run(['git', 'rev-list', '--count', 'HEAD..'.$upstream]);

        return [
            'available' => $behind > 0,
            'current' => $current,
            'remote' => $remote,
            'behind' => $behind,
            'branch' => $branch,
            'dirty' => $dirty,
            'can_update' => true,
            'update_command' => null,
            'message' => $behind > 0 ? "Sono disponibili {$behind} aggiornamenti." : 'Applicazione già aggiornata.',
        ];
    }

    /** @return array<int, array{command: string, output: string}> */
    public function update(): array
    {
        if ($this->isDocker()) {
            return $this->triggerDockerUpdate();
        }

        if (! $this->isGitRepository()) {
            throw new RuntimeException('Aggiornamento automatico non disponibile: questa cartella non è collegata a GitHub.');
        }

        $check = $this->check();

        if ($check['dirty']) {
            throw new RuntimeException('Aggiornamento bloccato: ci sono modifiche locali non salvate nel progetto.');
        }

        return [
            $this->runStep(['git', 'pull', '--ff-only']),
            $this->runStep(['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader']),
            $this->runStep(['npm', 'install']),
            $this->runStep(['npm', 'run', 'build']),
            $this->runStep(['php', 'artisan', 'migrate', '--force']),
            $this->runStep(['php', 'artisan', 'optimize:clear']),
        ];
    }

    private function checkDockerImage(): array
    {
        $current = (string) config('update.current_version', 'development');
        $remote = $this->latestSuccessfulDockerVersion();
        $versionKnown = preg_match('/^[0-9a-f]{40}$/i', $current) === 1;
        $available = $versionKnown && ! hash_equals(strtolower($current), strtolower($remote));
        $configured = filled(config('update.webhook_url'));

        $message = match (true) {
            ! $versionKnown => 'Questa immagine non contiene ancora un identificativo di versione. Aggiornala una volta da Portainer.',
            $available && ! $configured => 'È disponibile una nuova versione, ma il collegamento di aggiornamento non è ancora configurato.',
            $available => 'È disponibile una nuova versione. Puoi installarla ora.',
            default => 'Applicazione già aggiornata.',
        };

        return [
            'available' => $available,
            'current' => $this->shortVersion($current),
            'remote' => $this->shortVersion($remote),
            'behind' => $available ? 1 : 0,
            'branch' => (string) config('update.github_branch', 'main'),
            'dirty' => false,
            'can_update' => $configured,
            'update_command' => null,
            'message' => $message,
        ];
    }

    private function latestSuccessfulDockerVersion(): string
    {
        $repository = trim((string) config('update.github_repository'), '/');
        $workflow = rawurlencode((string) config('update.github_workflow'));

        $response = $this->githubRequest()->get(
            "https://api.github.com/repos/{$repository}/actions/workflows/{$workflow}/runs",
            [
                'branch' => config('update.github_branch', 'main'),
                'status' => 'success',
                'per_page' => 1,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Impossibile controllare la versione pubblicata su GitHub.');
        }

        $version = $response->json('workflow_runs.0.head_sha');

        if (! is_string($version) || preg_match('/^[0-9a-f]{40}$/i', $version) !== 1) {
            throw new RuntimeException('GitHub non ha restituito una versione Docker valida.');
        }

        return $version;
    }

    /** @return array<int, array{command: string, output: string}> */
    private function triggerDockerUpdate(): array
    {
        $url = (string) config('update.webhook_url');

        if ($url === '') {
            throw new RuntimeException('Il collegamento di aggiornamento con Portainer non è configurato.');
        }

        $request = Http::timeout(30)->acceptJson();

        if (! config('update.verify_tls', true)) {
            $request = $request->withoutVerifying();
        }

        if (filled(config('update.webhook_token'))) {
            $request = $request->withToken((string) config('update.webhook_token'));
        }

        $response = $request->post($url);

        if ($response->failed()) {
            throw new RuntimeException("Portainer ha rifiutato l'aggiornamento (HTTP {$response->status()}).");
        }

        return [[
            'command' => 'Aggiornamento Docker',
            'output' => 'Richiesta accettata. Il container verrà riavviato automaticamente; la pagina potrebbe risultare non disponibile per alcuni secondi.',
        ]];
    }

    private function githubRequest(): PendingRequest
    {
        $request = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);

        if (filled(config('update.github_token'))) {
            $request = $request->withToken((string) config('update.github_token'));
        }

        return $request;
    }

    private function shortVersion(string $version): string
    {
        return preg_match('/^[0-9a-f]{40}$/i', $version) === 1 ? substr($version, 0, 7) : $version;
    }

    private function upstream(): string
    {
        try {
            return $this->run(['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}']);
        } catch (RuntimeException) {
            return 'origin/'.$this->run(['git', 'branch', '--show-current']);
        }
    }

    private function isGitRepository(): bool
    {
        $process = new Process(['git', 'rev-parse', '--is-inside-work-tree'], base_path());
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) === 'true';
    }

    private function isDocker(): bool
    {
        return file_exists('/.dockerenv') || filled(getenv('APP_VERSION'));
    }

    private function run(array $command): string
    {
        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return trim($process->getOutput());
    }

    /** @return array{command: string, output: string} */
    private function runStep(array $command): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(900);
        $process->run();
        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            throw new RuntimeException($output);
        }

        return ['command' => implode(' ', $command), 'output' => $output];
    }
}
