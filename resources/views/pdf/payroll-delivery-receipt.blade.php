<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 36px; }
        body { color: #222; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { margin: 0 0 5px; font-size: 19px; }
        .subtitle { margin-bottom: 16px; color: #555; font-size: 10px; }
        .outcome { margin-bottom: 16px; padding: 11px; border: 1px solid #bbb; background: #f5f5f5; }
        .success { border-color: #82b596; background: #edf8f1; color: #185c32; }
        .warning { border-color: #d1a054; background: #fff8e8; color: #704b0c; }
        .error { border-color: #cb8585; background: #fff0f0; color: #7d2020; }
        .outcome strong { font-size: 12px; }
        .counts { margin-top: 6px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 6px; border: 1px solid #bbb; vertical-align: top; }
        th { background: #ececec; font-size: 8px; text-align: left; text-transform: uppercase; }
        .date { width: 88px; white-space: nowrap; }
        .status { width: 78px; }
        .error-message { margin-top: 3px; color: #7d2020; font-size: 8px; }
        .note { margin-top: 16px; color: #555; font-size: 8px; line-height: 1.4; }
    </style>
</head>
<body>
    @php
        $sent = $deliveries->where('status', 'sent')->count();
        $failed = $deliveries->where('status', 'failed')->count();
        $skipped = $deliveries->where('status', 'skipped_no_email')->count();
        $pending = $deliveries->whereIn('status', ['pending', 'sending'])->count();
        $completeWithoutErrors = $deliveries->isNotEmpty() && $failed === 0 && $pending === 0;
    @endphp

    <h1>Riepilogo invio buste paga</h1>
    <div class="subtitle">
        Periodo: <strong>{{ $distribution->period ?: 'non indicato' }}</strong>
        &nbsp;·&nbsp; Pratica #{{ $distribution->id }}
        &nbsp;·&nbsp; File: {{ $distribution->original_name }}<br>
        Pratica creata il {{ $distribution->created_at->format('d/m/Y H:i') }}
        @if ($distribution->user)&nbsp;·&nbsp; Operatore: {{ $distribution->user->name }}@endif
        &nbsp;·&nbsp; Riepilogo generato il {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="outcome {{ $failed > 0 ? 'error' : ($completeWithoutErrors ? 'success' : 'warning') }}">
        @if ($failed > 0)
            <strong>Invio completato con errori</strong>
        @elseif ($completeWithoutErrors)
            <strong>Invio elaborato senza errori rilevati</strong>
        @else
            <strong>Invio non ancora completato</strong>
        @endif
        <div class="counts">
            Email inviate: <strong>{{ $sent }}</strong> &nbsp;·&nbsp;
            Senza indirizzo email: <strong>{{ $skipped }}</strong> &nbsp;·&nbsp;
            Errori: <strong>{{ $failed }}</strong> &nbsp;·&nbsp;
            In attesa: <strong>{{ $pending }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Destinatario</th>
                <th>Indirizzo email</th>
                <th class="status">Esito</th>
                <th class="date">Data e ora</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveries as $delivery)
                @php($label = ['sent' => 'Inviata', 'failed' => 'Errore', 'skipped_no_email' => 'Senza email', 'pending' => 'In attesa', 'sending' => 'In invio'][$delivery->status] ?? ucfirst($delivery->status))
                <tr>
                    <td><strong>{{ $delivery->socio?->cognome }} {{ $delivery->socio?->nome }}</strong></td>
                    <td>{{ $delivery->email ?: '—' }}</td>
                    <td class="status">
                        {{ $label }}
                        @if ($delivery->status === 'failed' && $delivery->error)
                            <div class="error-message">{{ $delivery->error }}</div>
                        @endif
                    </td>
                    <td class="date">{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nessun tentativo di invio registrato.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">
        Questo documento riepiloga gli esiti registrati dall’applicazione durante la trasmissione al server di posta.
        “Inviata” indica che il sistema non ha ricevuto errori in fase di invio; non costituisce conferma di consegna
        nella casella del destinatario né ricevuta di lettura.
    </div>
</body>
</html>
