<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    /**
     * @return array{country_code: string, national: string, e164: string, display: string}
     */
    public function normalize(string $countryCode, string $mobile): array
    {
        $code = $this->normalizeCountryCode($countryCode);
        $digits = preg_replace('/\D/', '', $mobile) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Mobile number is required.');
        }

        $codeDigits = ltrim($code, '+');

        if (str_starts_with($digits, $codeDigits) && strlen($digits) > strlen($codeDigits) + 6) {
            $national = substr($digits, strlen($codeDigits));
        } else {
            $national = $digits;
        }

        $national = ltrim($national, '0');

        if ($code === '+91' && strlen($national) !== 10) {
            throw new InvalidArgumentException('Enter a valid 10-digit Indian mobile number.');
        }

        if (strlen($national) < 6 || strlen($national) > 14) {
            throw new InvalidArgumentException('Enter a valid mobile number.');
        }

        $e164 = $codeDigits . $national;

        return [
            'country_code' => $code,
            'national' => $national,
            'e164' => $e164,
            'display' => $code . ' ' . $national,
        ];
    }

    public function normalizeCountryCode(string $countryCode): string
    {
        $trimmed = trim($countryCode);
        if ($trimmed === '') {
            return '+91';
        }

        if (! str_starts_with($trimmed, '+')) {
            $trimmed = '+' . preg_replace('/\D/', '', $trimmed);
        }

        return $trimmed;
    }

    public function maskE164(string $e164): string
    {
        $digits = preg_replace('/\D/', '', $e164) ?? '';
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }
}
