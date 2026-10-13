<?php

namespace App\Domain\Admin\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * bcrypt only reads the first 72 bytes of a value. Longer passwords are
 * refused here, with a clear message, instead of being silently truncated.
 */
class FitsPasswordHashLimit implements ValidationRule
{
    public const MAX_BYTES = 72;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::MAX_BYTES) {
            $fail('Ce mot de passe est trop long. Utilisez 72 caractères au maximum (moins avec des accents).');
        }
    }
}
