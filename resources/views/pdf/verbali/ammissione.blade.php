<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 108px 46px 56px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.6; }
        h1 { font-size: 22px; margin-bottom: 32px; text-align: center; }
        .amount { font-weight: bold; }
        .social-summary { width: 100%; margin-top: 28px; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
        .social-summary th, .social-summary td { border: 1px solid #d1d5db; padding: 7px 9px; }
        .social-summary th { width: 72%; text-align: left; font-weight: normal; background: #f9fafb; }
        .social-summary td { width: 28%; text-align: right; font-weight: bold; }
        .social-summary-spacer td { padding: 5px 0; border-left: 0; border-right: 0; background: #ffffff; }
        .footer { margin-top: 80px; display: flex; justify-content: space-between; }
        .document-header { position: fixed; top: -82px; left: 0; right: 0; height: 62px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 50px; max-width: 150px; margin-right: 16px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    <h1>{{ $verbale->titolo }}</h1>

    <p>
        In data {{ $socio->data_ammissione?->format('d/m/Y') }},
        {{ $socio->nome_completo }} con codice fiscale {{ $socio->codice_fiscale }}
        viene ammesso in qualità di {{ \App\Models\Socio::TIPOLOGIE[$socio->tipologia] ?? $socio->tipologia }}
        presso la Cooperativa Sociale a Responsabilità Limitata FTM.
    </p>

    <p>
        In concomitanza dell'ammissione il Socio ha provveduto a versare
        <span class="amount">{{ number_format((float) $socio->capitale_versato, 2, ',', '.') }} EUR</span>.
    </p>

    @include('pdf.verbali.partials.riepilogo-sociale')

    <div class="footer">
        <div>Il Presidente</div>
        <div>Il Socio</div>
    </div>
</body>
</html>
