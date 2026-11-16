<?php

namespace App\Http\Middleware;

use App\Support\Arabic;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Cashiers on an Arabic keyboard type ٢٠ or ١٥٫٥ — convert numeric-looking input
 * (prices, amounts, phone numbers, search terms) to ASCII digits. Text such as
 * product names is left untouched, and passwords are never modified.
 */
class ConvertArabicDigits extends TransformsRequest
{
    protected array $except = ['password', 'password_confirmation', 'current_password'];

    protected function transform($key, $value)
    {
        if (! is_string($value) || in_array($key, $this->except, true)) {
            return $value;
        }

        $converted = Arabic::normalizeDigits($value);

        // Only numeric-looking values: digits, spaces, + - . , ( ) and "/" (dates).
        return preg_match('/^[\d\s+\-.,()\/]*$/', $converted) ? $converted : $value;
    }
}
