<x-filament-panels::page>
    {{ $this->content }}

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
