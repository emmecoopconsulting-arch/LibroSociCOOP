<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 92px 28px 46px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        h3 { font-size: 11px; margin: 0 0 6px; }
        p { margin: 0 0 8px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .document-header { position: fixed; top: -70px; left: 0; right: 0; height: 52px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 42px; max-width: 140px; margin-right: 14px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
        .muted { color: #6b7280; }
        .site { page-break-inside: avoid; margin-bottom: 12px; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>{{ $order->titolo }}</h1>
    <p><strong>Data servizio:</strong> {{ $order->data_servizio?->format('d/m/Y') }} | <strong>Stato:</strong> {{ \App\Models\WorkOrder::STATI[$order->stato] ?? $order->stato }}</p>

    @if ($order->note)
        <p><strong>Note generali:</strong> {{ $order->note }}</p>
    @endif

    <h2>Cantieri di lavoro</h2>
    @forelse ($order->sites as $site)
        <div class="site">
            <h3>{{ $site->nome }}</h3>
            <table>
                <tbody>
                    <tr>
                        <th style="width: 18%;">Luogo</th>
                        <td>{{ $site->luogo }}</td>
                        <th style="width: 18%;">Orario</th>
                        <td>{{ substr((string) $site->orario_inizio, 0, 5) }} - {{ substr((string) $site->orario_fine, 0, 5) }}</td>
                    </tr>
                    <tr>
                        <th>Mezzo</th>
                        <td colspan="3">
                            @if ($site->vehicle)
                                {{ $site->vehicle->descrizione }} - {{ \App\Models\WorkVehicle::TIPI[$site->vehicle->tipo] ?? $site->vehicle->tipo }}
                            @else
                                <span class="muted">Non indicato</span>
                            @endif
                        </td>
                    </tr>
                    @if ($site->note)
                        <tr>
                            <th>Note</th>
                            <td colspan="3">{{ $site->note }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <table>
                <thead>
                    <tr>
                        <th>Persone assegnate</th>
                        <th>Codice socio</th>
                        <th>Mansione</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($site->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->socio?->nome_completo }}</td>
                            <td>{{ $assignment->socio?->codice_socio }}</td>
                            <td>{{ $assignment->socio?->mansione }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">Nessuna persona assegnata.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted">Nessun cantiere inserito.</p>
    @endforelse

    <h2>Assenze</h2>
    <table>
        <thead>
            <tr>
                <th>Socio</th>
                <th>Tipo</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->absences as $absence)
                <tr>
                    <td>{{ $absence->socio?->nome_completo }}</td>
                    <td>{{ \App\Models\WorkAbsence::TIPI[$absence->tipo] ?? $absence->tipo }}</td>
                    <td>{{ $absence->note }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">Nessuna assenza inserita.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
