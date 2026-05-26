<?php

namespace App\Rules;

use App\Services\CodiceFiscaleParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CodiceFiscale implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CodiceFiscaleParser::class)->isValid((string) $value)) {
            $fail('Il codice fiscale non è formalmente valido.');
        }
    }
}
