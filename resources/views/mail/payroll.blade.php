<!DOCTYPE html>
<html lang="it">
<body>
    <p>Gentile {{ $socio->nome }} {{ $socio->cognome }},</p>
    <p>in allegato trova la busta paga relativa al periodo {{ $period }}.</p>
    @if (filled($additionalMessage))
        <div>{!! nl2br(e($additionalMessage)) !!}</div>
    @endif
    <p>Questa comunicazione contiene dati personali. La preghiamo di non inoltrarla.</p>
</body>
</html>
