<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Rules\FitsPasswordHashLimit;

/**
 * Password policy of back-office administrators (decision 0009): at least
 * 8 characters, no imposed composition, and never more than bcrypt can hash.
 */
class AdminPassword
{
    public const MIN_LENGTH = 8;

    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'min:'.self::MIN_LENGTH, new FitsPasswordHashLimit];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'required' => 'Choisissez un mot de passe.',
            'min' => 'Le mot de passe doit comporter au moins '.self::MIN_LENGTH.' caractères.',
        ];
    }
}
