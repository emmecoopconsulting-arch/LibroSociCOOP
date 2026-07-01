<x-filament-panels::page>
    {{ $this->content }}

    <x-filament::section>
        <x-slot name="heading">Lavori di assegnazione</x-slot>
        <x-slot name="description">Ultime 50 distribuzioni caricate, con avanzamento delle associazioni e degli invii.</x-slot>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">Lavoro</th>
                        <th class="px-4 py-3 font-medium">File</th>
                        <th class="px-4 py-3 font-medium">Assegnazioni</th>
                        <th class="px-4 py-3 font-medium">Inviate</th>
                        <th class="px-4 py-3 font-medium">Senza email</th>
                        <th class="px-4 py-3 font-medium">Errori</th>
                        <th class="px-4 py-3 font-medium">Stato</th>
                        <th class="px-4 py-3 font-medium">Data</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->distributions() as $item)
                        <tr wire:key="distribution-{{ $item->id }}" class="{{ $distributionId === $item->id ? 'bg-primary-50 dark:bg-primary-950/20' : '' }}">
                            <td class="px-4 py-3 font-semibold">Buste {{ $item->period ?: '#' . $item->id }}</td>
                            <td class="max-w-xs truncate px-4 py-3" title="{{ $item->original_name }}">{{ $item->original_name }}</td>
                            <td class="px-4 py-3">{{ $item->assigned_pages_count }}/{{ $item->pages_count }}</td>
                            <td class="px-4 py-3 text-success-700">{{ $item->sent_count }}</td>
                            <td class="px-4 py-3 text-warning-700">{{ $item->skipped_count }}</td>
                            <td class="px-4 py-3 text-danger-700">{{ $item->failed_count }}</td>
                            <td class="px-4 py-3">{{ ucfirst($item->status) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="font-medium text-primary-600 hover:underline"
                                    wire:click="selectDistribution({{ $item->id }})"
                                >
                                    Apri
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nessun lavoro caricato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @php($distribution = $this->currentDistribution())

    @if ($distribution)
        <x-filament::section>
            <x-slot name="heading">Revisione associazioni</x-slot>
            <x-slot name="description">
                {{ $distribution->original_name }} — {{ $distribution->period }}.
                L’invio è consentito solo quando ogni pagina è associata.
            </x-slot>

            @if ($distribution->error)
                <div class="mb-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                    {{ $distribution->error }}
                </div>
            @endif

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">Pagina</th>
                            <th class="px-4 py-3 font-medium">Socio destinatario</th>
                            <th class="px-4 py-3 font-medium">Affidabilità</th>
                            <th class="px-4 py-3 font-medium">Motivo</th>
                            <th class="px-4 py-3 font-medium">Consegna</th>
                            <th class="px-4 py-3 font-medium">Testo OCR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($distribution->pages as $page)
                            <tr wire:key="payroll-page-{{ $page->id }}">
                                <td class="px-4 py-3 font-semibold">{{ $page->page_number }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/20 dark:bg-gray-900"
                                        wire:change="setSocio({{ $page->id }}, $event.target.value)"
                                        @disabled($distribution->deliveries->contains('status', 'sent'))
                                    >
                                        <option value="">— Da associare —</option>
                                        @foreach ($this->socioOptions() as $id => $label)
                                            <option value="{{ $id }}" @selected((int) $page->socio_id === (int) $id)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="{{ $page->match_confidence >= 95 ? 'text-success-700' : ($page->match_confidence >= 80 ? 'text-warning-700' : 'text-danger-700') }}">
                                        {{ $page->match_confidence }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $page->match_reason ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @php($delivery = $distribution->deliveries->firstWhere('socio_id', $page->socio_id))
                                    @php($document = $distribution->documents->firstWhere('socio_id', $page->socio_id))
                                    @if (! $page->socio_id)
                                        <span class="text-danger-700">Da assegnare</span>
                                    @elseif ($delivery?->status === 'sent')
                                        <span class="text-success-700">Email inviata</span>
                                    @elseif ($delivery?->status === 'failed')
                                        <span class="text-danger-700" title="{{ $delivery->error }}">Errore invio</span>
                                    @elseif ($delivery?->status === 'skipped_no_email' || blank($page->socio?->email))
                                        <span class="text-warning-700">Archiviata, senza email</span>
                                    @else
                                        <span>Archiviata nei documenti</span>
                                    @endif
                                    @if ($document)
                                        <a
                                            class="mt-1 block text-xs text-primary-600 hover:underline"
                                            href="{{ route('local-files.file', ['path' => \Illuminate\Support\Facades\Crypt::encryptString($document->file_path), 'download' => true]) }}"
                                            target="_blank"
                                        >
                                            Scarica PDF
                                        </a>
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-3">
                                    <details>
                                        <summary class="cursor-pointer text-primary-600">Mostra testo</summary>
                                        <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap text-xs">{{ $page->ocr_text }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($distribution->deliveries->isNotEmpty())
                <div class="mt-5 text-sm">
                    Inviate: {{ $distribution->deliveries->where('status', 'sent')->count() }} ·
                    Errori: {{ $distribution->deliveries->where('status', 'failed')->count() }}
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
