<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Stato versione
        </x-slot>

        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500">Branch</div>
                    <div class="mt-1 font-medium">{{ $status['branch'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Versione locale</div>
                    <div class="mt-1 font-medium">{{ $status['current'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Versione GitHub</div>
                    <div class="mt-1 font-medium">{{ $status['remote'] ?? '-' }}</div>
                </div>
            </div>

            <div @class([
                'rounded-lg border px-4 py-3 text-sm',
                'border-amber-200 bg-amber-50 text-amber-900' => $status['available'] ?? false,
                'border-red-200 bg-red-50 text-red-900' => $status['dirty'] ?? false,
                'border-green-200 bg-green-50 text-green-900' => ! ($status['available'] ?? false) && ! ($status['dirty'] ?? false),
            ])>
                {{ $status['message'] ?? 'Controllo aggiornamenti non ancora eseguito.' }}

                @if ($status['dirty'] ?? false)
                    <div class="mt-1">
                        Ci sono modifiche locali: l'aggiornamento automatico e bloccato per non sovrascriverle.
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap gap-3">
                <x-filament::button icon="heroicon-o-arrow-path" wire:click="check">
                    Controlla aggiornamenti
                </x-filament::button>

                <x-filament::button
                    color="success"
                    icon="heroicon-o-cloud-arrow-down"
                    wire:click="update"
                    wire:confirm="Vuoi aggiornare l'applicazione da GitHub?"
                    :disabled="! ($status['available'] ?? false) || ($status['dirty'] ?? false)"
                >
                    Aggiorna ora
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    @if (filled($log))
        <x-filament::section>
            <x-slot name="heading">
                Log aggiornamento
            </x-slot>

            <div class="space-y-4">
                @foreach ($log as $step)
                    <div class="rounded-lg border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 font-mono text-xs">
                            {{ $step['command'] }}
                        </div>
                        <pre class="max-h-72 overflow-auto whitespace-pre-wrap p-4 text-xs">{{ $step['output'] }}</pre>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
