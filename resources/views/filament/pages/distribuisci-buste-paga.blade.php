<x-filament-panels::page>
    {{ $this->content }}

    <x-filament::section>
        <x-slot name="heading">Lavori di assegnazione</x-slot>
        <x-slot name="description">Ultime 50 distribuzioni caricate, con avanzamento delle associazioni e degli invii.</x-slot>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-separate border-spacing-0 text-left text-sm">
                <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <tr>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Lavoro</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">File</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Assegnazioni</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Inviate</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Senza email</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 text-center font-semibold dark:border-white/10">Errori</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Stato</th>
                        <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Data</th>
                        <th class="border-b border-gray-200 px-5 py-4 dark:border-white/10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->distributions() as $item)
                        @php($statusLabel = ['processing' => 'Elaborazione', 'review' => 'Da verificare', 'sending' => 'Invio', 'partial' => 'Con errori', 'sent' => 'Completato', 'failed' => 'OCR fallito'][$item->status] ?? ucfirst($item->status))
                        <tr wire:key="distribution-{{ $item->id }}" class="transition hover:bg-gray-50 dark:hover:bg-white/5 {{ $distributionId === $item->id ? 'bg-primary-50 dark:bg-primary-950/20' : '' }}">
                            <td class="border-b border-r border-gray-100 px-5 py-4 font-semibold text-gray-950 dark:border-white/10 dark:text-white">Buste {{ $item->period ?: '#' . $item->id }}</td>
                            <td class="max-w-xs truncate border-b border-r border-gray-100 px-5 py-4 text-gray-600 dark:border-white/10 dark:text-gray-300" title="{{ $item->original_name }}">{{ $item->original_name }}</td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10">
                                <span class="inline-flex min-w-14 justify-center rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $item->assigned_pages_count }}/{{ $item->pages_count }}</span>
                            </td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-success-50 px-3 py-1 font-semibold text-success-700 dark:bg-success-400/10">{{ $item->sent_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-warning-50 px-3 py-1 font-semibold text-warning-700 dark:bg-warning-400/10">{{ $item->skipped_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 text-center dark:border-white/10"><span class="inline-flex min-w-9 justify-center rounded-full bg-danger-50 px-3 py-1 font-semibold text-danger-700 dark:bg-danger-400/10">{{ $item->failed_count }}</span></td>
                            <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold {{ $item->status === 'sent' ? 'bg-success-50 text-success-700' : ($item->status === 'partial' || $item->status === 'failed' ? 'bg-danger-50 text-danger-700' : 'bg-primary-50 text-primary-700') }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap border-b border-r border-gray-100 px-5 py-4 text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td class="border-b border-gray-100 px-5 py-4 text-right dark:border-white/10">
                                <button
                                    type="button"
                                    class="rounded-lg border border-primary-300 bg-white px-3 py-1.5 font-semibold text-primary-700 shadow-sm transition hover:bg-primary-50 dark:bg-gray-900"
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

            @php($totalRecipients = $distribution->pages->whereNotNull('socio_id')->pluck('socio_id')->unique()->count())
            @php($processedRecipients = $distribution->sent_count + $distribution->failed_count + $distribution->skipped_count)
            @php($progress = $totalRecipients > 0 ? min(100, (int) round(($processedRecipients / $totalRecipients) * 100)) : 0)

            <div class="mb-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">Avanzamento invio</span>
                    <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ $processedRecipients }}/{{ $totalRecipients }} destinatari · {{ $progress }}%</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-primary-600 transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>
                <div wire:loading wire:target="distribute,retryFailed" class="mt-3 w-full">
                    <div class="mb-1 text-xs font-medium text-primary-700">Invio in corso, non chiudere la pagina…</div>
                    <div class="h-2 overflow-hidden rounded-full bg-primary-100">
                        <div class="h-full w-full animate-pulse rounded-full bg-primary-500"></div>
                    </div>
                </div>
            </div>

            @if ($distribution->failed_count > 0)
                <div class="mb-5 rounded-xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-400/20 dark:bg-danger-400/10">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold text-danger-800 dark:text-danger-300">{{ $distribution->failed_count }} invii non riusciti</div>
                            <div class="mt-1 text-sm text-danger-700 dark:text-danger-300">Controlla le motivazioni e riprova soltanto le email fallite.</div>
                        </div>
                        <button type="button" wire:click="retryFailed" wire:loading.attr="disabled" wire:target="retryFailed" class="rounded-lg bg-warning-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-warning-600 disabled:opacity-50">
                            Riprova fallite
                        </button>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ($distribution->deliveries->where('status', 'failed') as $failedDelivery)
                            <div class="rounded-lg border border-danger-200 bg-white p-3 text-sm dark:bg-gray-900">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $failedDelivery->socio?->nome_completo ?: 'Socio non disponibile' }} — {{ $failedDelivery->email }}</div>
                                <div class="mt-1 break-words font-mono text-xs text-danger-700">{{ $failedDelivery->error ?: 'Il server SMTP non ha restituito una motivazione.' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] border-separate border-spacing-0 text-left text-sm">
                    <thead class="bg-gray-100 text-xs uppercase tracking-wide text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        <tr>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Pagina</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Socio destinatario</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Affidabilità</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Motivo</th>
                            <th class="border-b border-r border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Consegna</th>
                            <th class="border-b border-gray-200 px-5 py-4 font-semibold dark:border-white/10">Testo OCR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($distribution->pages as $page)
                            <tr wire:key="payroll-page-{{ $page->id }}" class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="border-b border-r border-gray-100 px-5 py-4 text-center font-bold dark:border-white/10">{{ $page->page_number }}</td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
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
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                    <span class="inline-flex rounded-full px-3 py-1 font-semibold {{ $page->match_confidence >= 95 ? 'bg-success-50 text-success-700' : ($page->match_confidence >= 80 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700') }}">
                                        {{ $page->match_confidence }}%
                                    </span>
                                </td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">{{ $page->match_reason ?: '—' }}</td>
                                <td class="border-b border-r border-gray-100 px-5 py-4 dark:border-white/10">
                                    @php($delivery = $distribution->deliveries->firstWhere('socio_id', $page->socio_id))
                                    @php($document = $distribution->documents->firstWhere('socio_id', $page->socio_id))
                                    @if (! $page->socio_id)
                                        <span class="text-danger-700">Da assegnare</span>
                                    @elseif ($delivery?->status === 'sent')
                                        <span class="text-success-700">Email inviata</span>
                                    @elseif ($delivery?->status === 'failed')
                                        <span class="inline-flex rounded-full bg-danger-50 px-3 py-1 font-semibold text-danger-700">Errore invio</span>
                                        <details class="mt-2 max-w-sm">
                                            <summary class="cursor-pointer text-xs font-semibold text-danger-700">Mostra motivazione</summary>
                                            <div class="mt-1 break-words rounded-md bg-danger-50 p-2 font-mono text-xs text-danger-800">{{ $delivery->error ?: 'Motivazione non disponibile.' }}</div>
                                        </details>
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
                                <td class="max-w-sm border-b border-gray-100 px-5 py-4 dark:border-white/10">
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
