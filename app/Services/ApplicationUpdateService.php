<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class ApplicationUpdateService
{
    /**
     * @return array{available: bool, current: string, remote: string, behind: int, branch: string, dirty: bool, message: string}
     */
    public function check(): array
    {
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
            'message' => $behind > 0
                ? "Sono disponibili {$behind} aggiornamenti."
                : 'Applicazione già aggiornata.',
        ];
    }

    /**
     * @return array<int, array{command: string, output: string}>
     */
    public function update(): array
    {
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

    private function upstream(): string
    {
        try {
            return $this->run(['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}']);
        } catch (RuntimeException) {
            $branch = $this->run(['git', 'branch', '--show-current']);

            return 'origin/'.$branch;
        }
    }

    /**
     * @param  array<int, string>  $command
     */
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

    /**
     * @param  array<int, string>  $command
     * @return array{command: string, output: string}
     */
    private function runStep(array $command): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(900);
        $process->run();

        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            throw new RuntimeException($output);
        }

        return [
            'command' => implode(' ', $command),
            'output' => $output,
        ];
    }
}
