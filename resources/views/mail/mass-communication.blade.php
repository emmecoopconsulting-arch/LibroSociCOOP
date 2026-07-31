<!DOCTYPE html>
<html lang="it">
<body>
    <p>Gentile {{ $socio->nome }} {{ $socio->cognome }},</p>
    <div>{!! nl2br(e($communication->body)) !!}</div>
</body>
</html>
