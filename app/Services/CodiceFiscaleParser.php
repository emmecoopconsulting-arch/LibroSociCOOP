<?php

namespace App\Services;

use App\Models\Comune;
use App\Models\Socio;
use Carbon\CarbonImmutable;

class CodiceFiscaleParser
{
    private const OMOCODIA = [
        'L' => '0',
        'M' => '1',
        'N' => '2',
        'P' => '3',
        'Q' => '4',
        'R' => '5',
        'S' => '6',
        'T' => '7',
        'U' => '8',
        'V' => '9',
    ];

    private const MONTHS = [
        'A' => 1,
        'B' => 2,
        'C' => 3,
        'D' => 4,
        'E' => 5,
        'H' => 6,
        'L' => 7,
        'M' => 8,
        'P' => 9,
        'R' => 10,
        'S' => 11,
        'T' => 12,
    ];

    private const ODD = [
        '0' => 1,
        '1' => 0,
        '2' => 5,
        '3' => 7,
        '4' => 9,
        '5' => 13,
        '6' => 15,
        '7' => 17,
        '8' => 19,
        '9' => 21,
        'A' => 1,
        'B' => 0,
        'C' => 5,
        'D' => 7,
        'E' => 9,
        'F' => 13,
        'G' => 15,
        'H' => 17,
        'I' => 19,
        'J' => 21,
        'K' => 2,
        'L' => 4,
        'M' => 18,
        'N' => 20,
        'O' => 11,
        'P' => 3,
        'Q' => 6,
        'R' => 8,
        'S' => 12,
        'T' => 14,
        'U' => 16,
        'V' => 10,
        'W' => 22,
        'X' => 25,
        'Y' => 24,
        'Z' => 23,
    ];

    public function isValid(?string $codiceFiscale): bool
    {
        $cf = strtoupper(trim((string) $codiceFiscale));

        if (! preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $cf)) {
            return false;
        }

        return $this->checkControlChar($cf) && $this->birthDate($cf) !== null;
    }

    public function birthDate(string $codiceFiscale): ?CarbonImmutable
    {
        $cf = strtoupper($codiceFiscale);
        $month = self::MONTHS[$cf[8]] ?? null;
        $day = (int) $this->decodeOmocodia(substr($cf, 9, 2));

        if ($month === null) {
            return null;
        }

        if ($day > 40) {
            $day -= 40;
        }

        $year = (int) $this->decodeOmocodia(substr($cf, 6, 2));
        $currentTwoDigits = (int) now()->format('y');
        $century = $year <= $currentTwoDigits ? 2000 : 1900;

        try {
            return CarbonImmutable::createSafe($century + $year, $month, $day);
        } catch (\Throwable) {
            return null;
        }
    }

    public function codiceCatastale(string $codiceFiscale): ?string
    {
        $cf = strtoupper($codiceFiscale);

        if (! preg_match('/^[A-Z0-9]{16}$/', $cf)) {
            return null;
        }

        return $cf[11].$this->decodeOmocodia(substr($cf, 12, 3));
    }

    public function comuneNascita(string $codiceFiscale): ?Comune
    {
        $codice = $this->codiceCatastale($codiceFiscale);

        return $codice ? Comune::query()->where('codice_catastale', $codice)->first() : null;
    }

    public function applyToSocio(Socio $socio): void
    {
        if (! $this->isValid($socio->codice_fiscale)) {
            return;
        }

        $socio->data_nascita = $this->birthDate($socio->codice_fiscale);

        if ($comune = $this->comuneNascita($socio->codice_fiscale)) {
            $socio->comune_nascita_id = $comune->id;
            $socio->luogo_nascita = $comune->denominazione;

            return;
        }

        $codiceCatastale = $this->codiceCatastale($socio->codice_fiscale);

        if (str_starts_with((string) $codiceCatastale, 'Z')) {
            $socio->luogo_nascita = "Estero ({$codiceCatastale})";
        }
    }

    private function checkControlChar(string $cf): bool
    {
        $sum = 0;

        for ($i = 0; $i < 15; $i++) {
            $char = $cf[$i];
            $sum += $i % 2 === 0
                ? self::ODD[$char]
                : (ctype_digit($char) ? (int) $char : ord($char) - 65);
        }

        return chr(($sum % 26) + 65) === $cf[15];
    }

    private function decodeOmocodia(string $value): string
    {
        return strtr(strtoupper($value), self::OMOCODIA);
    }
}
