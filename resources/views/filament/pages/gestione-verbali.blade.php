<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section>
            <div class="text-sm text-gray-500">Verbali da generare</div>
            <div class="mt-2 text-3xl font-semibold">{{ $this->missingCount() }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Verbali già generati</div>
            <div class="mt-2 text-3xl font-semibold">{{ $this->generatedCount() }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">
            Verbali da generare
        </x-slot>

        <div class="mb-4 flex justify-end">
            <x-filament::button icon="heroicon-o-document-check" wire:click="generateAll">
                Genera tutti i verbali mancanti
            </x-filament::button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-[860px] w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 font-medium">Codice</th>
                        <th class="px-4 py-3 font-medium">Socio</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Data verbale</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->pendingVerbales() as $verbale)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $verbale->socio->codice_socio }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $verbale->socio->cognome }} {{ $verbale->socio->nome }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $this->verbaleTipoLabel($verbale->tipo) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $verbale->data_verbale?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <x-filament::button size="sm" icon="heroicon-o-document-arrow-down" wire:click="generateVerbale({{ $verbale->id }})">
                                    Genera verbale
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-center text-gray-500" colspan="5">
                                Non ci sono verbali mancanti.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Soci con verbale di ammissione mancante
        </x-slot>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-[860px] w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 font-medium">Codice</th>
                        <th class="px-4 py-3 font-medium">Socio</th>
                        <th class="px-4 py-3 font-medium">Codice fiscale</th>
                        <th class="px-4 py-3 font-medium">Ammesso il</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->missingSoci() as $socio)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $socio->codice_socio }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $socio->cognome }} {{ $socio->nome }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $socio->codice_fiscale }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $socio->data_ammissione?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <x-filament::button size="sm" icon="heroicon-o-document-arrow-down" wire:click="generate({{ $socio->id }})">
                                    Crea e genera
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-6 text-center text-gray-500" colspan="5">
                                Non ci sono ammissioni senza verbale generato.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
