<?php

namespace App\Rules;

use App\Enums\ActivityStatusEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActivityStatusRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (ActivityStatusEnum::tryFrom($value) === null) {
            $fail("The {$attribute} must be a valid activity status.");
        }
    }
}
