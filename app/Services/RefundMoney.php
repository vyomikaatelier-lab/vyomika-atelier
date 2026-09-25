<?php

namespace App\Services;

use InvalidArgumentException;

final class RefundMoney
{
    public const MINIMUM_PAISE = 100;

    public static function paiseFromDecimal(string $amount): int
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Money must be a non-negative amount with at most two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    public static function formatRupees(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function linePaise(
        int $unitPaise,
        int $lineTotalPaise,
        int $alreadyRefundedPaise,
        int $quantity,
        int $remainingQuantity,
    ): int {
        if ($quantity < 1 || $remainingQuantity < 1 || $quantity > $remainingQuantity) {
            throw new InvalidArgumentException('Refund quantity is outside the remaining line quantity.');
        }

        $remainingPaise = $lineTotalPaise - $alreadyRefundedPaise;

        if ($remainingPaise < 0) {
            throw new InvalidArgumentException('Line refunds already exceed the line total.');
        }

        if ($quantity === $remainingQuantity) {
            return $remainingPaise;
        }

        $partial = $unitPaise * $quantity;

        return min($partial, $remainingPaise);
    }
}
