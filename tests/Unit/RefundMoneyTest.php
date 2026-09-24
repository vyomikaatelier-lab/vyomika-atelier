<?php

namespace Tests\Unit;

use App\Services\RefundMoney;
use InvalidArgumentException;
use Tests\TestCase;

class RefundMoneyTest extends TestCase
{
    public function test_decimal_strings_convert_to_paise_without_float_rounding(): void
    {
        $this->assertSame(1010, RefundMoney::paiseFromDecimal('10.10'));
        $this->assertSame(1010, RefundMoney::paiseFromDecimal('10.1'));
        $this->assertSame(1000, RefundMoney::paiseFromDecimal('10'));
        $this->assertSame(1, RefundMoney::paiseFromDecimal('0.01'));
        $this->assertSame(19900, RefundMoney::paiseFromDecimal('199.00'));
        $this->assertSame('10.10', RefundMoney::formatRupees(1010));
        $this->assertSame('0.01', RefundMoney::formatRupees(1));
    }

    public function test_invalid_money_is_rejected(): void
    {
        foreach (['', '-1.00', '10.001', '10.10.1', '1e2', ' 10.00'] as $amount) {
            try {
                RefundMoney::paiseFromDecimal($amount);
                $this->fail('Expected invalid money to be rejected: '.$amount);
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_last_quantity_on_a_line_refunds_the_remaining_paise(): void
    {
        $this->assertSame(333, RefundMoney::linePaise(333, 1000, 0, 1, 3));
        $this->assertSame(333, RefundMoney::linePaise(333, 1000, 333, 1, 2));
        $this->assertSame(334, RefundMoney::linePaise(333, 1000, 666, 1, 1));
    }

    public function test_quantity_above_the_remainder_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RefundMoney::linePaise(100, 100, 0, 2, 1);
    }
}
