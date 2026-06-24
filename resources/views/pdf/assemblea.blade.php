<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 108px 46px 56px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.55; }
        h1 { font-size: 22px; margin-bottom: 22px; text-align: center; }
        h2 { font-size: 15px; margin-top: 24px; margin-bottom: 10px; }
        p { margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; line-height: 1.35; margin: 14px 0; }
        th, td { border: 1px solid #d1d5db; padding: 7px 8px; vertical-align: top; }
        th { text-align: left; font-weight: bold; background: #f9fafb; }
        .muted { color: #4b5563; }
        .meta { margin-bottom: 16px; }
        .document-header { position: fixed; top: -82px; left: 0; right: 0; height: 62px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 50px; max-width: 150px; margin-right: 16px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
        .signature-footer { margin-top: 76px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>{{ $assemblea->titolo }}</h1>

    <div class="meta">
        <p>
            In data {{ $assemblea->data_assemblea?->format('d/m/Y') }}
            @if ($assemblea->started_at)
                alle ore {{ $assemblea->started_at->format('H:i') }}
            @endif
            si apre l'assemblea
            @if ($assemblea->modalita)
                in modalità {{ \App\Models\Assemblea::MODALITA[$assemblea->modalita] ?? $assemblea->modalita }}
            @endif
            @if ($assemblea->luogo)
                presso {{ $assemblea->luogo }}
            @endif
            .
        </p>

        @if ($assemblea->presidente || $assemblea->segretario)
            <p>
                @if ($assemblea->presidente)
                    Presidente: {{ $assemblea->presidente }}.
                @endif
                @if ($assemblea->segretario)
                    Segretario: {{ $assemblea->segretario }}.
                @endif
            </p>
        @endif
    </div>

    @if ($assemblea->note)
        <p class="muted">{{ $assemblea->note }}</p>
    @endif

    @if ($assemblea->presenze->isNotEmpty())
        <h2>Appello soci</h2>

        <table>
            <thead>
                <tr>
                    <th>Socio</th>
                    <th>Stato</th>
                    <th>Ora rilevazione</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assemblea->presenze as $presenza)
                    <tr>
                        <td>
                            {{ $presenza->socio?->codice_socio }}<br>
                            {{ $presenza->socio?->cognome }} {{ $presenza->socio?->nome }}
                        </td>
                        <td>{{ \App\Models\AssembleaPresenza::STATI[$presenza->stato] ?? $presenza->stato }}</td>
                        <td>{{ $presenza->presente_at?->format('H:i') ?: '-' }}</td>
                        <td>{{ $presenza->note ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($assemblea->puntiOdg->isNotEmpty())
        <h2>Ordine del giorno</h2>

        @foreach ($assemblea->puntiOdg as $punto)
            <h2>{{ $punto->ordine }}. {{ $punto->titolo }}</h2>

            @if ($punto->descrizione)
                <p>{{ $punto->descrizione }}</p>
            @endif

            @if ($punto->discussione)
                <p>{{ $punto->discussione }}</p>
            @endif

            <p>
                Esito: {{ \App\Models\AssembleaPuntoOdg::ESITI[$punto->esito] ?? $punto->esito }}.
                @if (! is_null($punto->voti_favorevoli) || ! is_null($punto->voti_contrari) || ! is_null($punto->astenuti))
                    Favorevoli: {{ $punto->voti_favorevoli ?? 0 }},
                    contrari: {{ $punto->voti_contrari ?? 0 }},
                    astenuti: {{ $punto->astenuti ?? 0 }}.
                @endif
            </p>
        @endforeach
    @endif

    <h2>Variazioni deliberate</h2>

    @if ($assemblea->variations->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Socio</th>
                    <th>Variazione</th>
                    <th>Data effetto</th>
                    <th>Dettagli</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assemblea->variations as $variation)
                    <tr>
                        <td>
                            {{ $variation->socio?->codice_socio }}<br>
                            {{ $variation->socio?->cognome }} {{ $variation->socio?->nome }}
                        </td>
                        <td>{{ $tipoLabels[$variation->tipo] ?? $variation->tipo }}</td>
                        <td>{{ $variation->data_effetto?->format('d/m/Y') }}</td>
                        <td>
                            @if ($variation->tipo_contratto)
                                Contratto: {{ $variation->tipo_contratto }}<br>
                            @endif
                            @if ($variation->data_inizio)
                                Inizio: {{ $variation->data_inizio->format('d/m/Y') }}<br>
                            @endif
                            @if ($variation->data_fine)
                                Fine: {{ $variation->data_fine->format('d/m/Y') }}<br>
                            @endif
                            @if ($variation->ore_settimanali)
                                Ore: {{ number_format((float) $variation->ore_settimanali, 2, ',', '.') }}<br>
                            @endif
                            @if ($variation->note)
                                {{ $variation->note }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>
            Per ciascuna variazione viene generato anche il relativo verbale individuale del socio, con data verbale
            corrispondente alla data dell'assemblea e decorrenza indicata nella singola riga.
        </p>
    @else
        <p>Nessuna variazione socio deliberata.</p>
    @endif

    @if ($assemblea->closed_at)
        <p>L'assemblea viene chiusa alle ore {{ $assemblea->closed_at->format('H:i') }}.</p>
    @endif

    <div class="signature-footer">
        <div>Il Presidente</div>
        <div>Il Segretario</div>
    </div>
</body>
</html>
