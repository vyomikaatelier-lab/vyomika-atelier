<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class ReconciliationEvidenceTest extends TestCase
{
    public function test_clean_reconciliation_values_have_no_evidence(): void
    {
        $clean = [
            null,
            false,
            '',
            [],
            new \stdClass,
            ['extra_payment_ids' => [], 'conflicting_payment_ids' => []],
            ['flag' => false, 'count' => 0, 'amount' => 0.0, 'note' => '', 'nested' => []],
            ['wrapper' => ['inner' => ['empty' => null, 'off' => false, 'zero' => 0]]],
        ];

        foreach ($clean as $value) {
            $this->assertFalse(Order::reconciliationValueHasEvidence($value));
        }
    }

    public function test_non_empty_reconciliation_values_are_evidence(): void
    {
        $evidence = [
            true,
            1,
            1.5,
            -1,
            'manual',
            '0',
            ' ',
            ['extra_payment_ids' => ['pay_hidden']],
            ['conflicting_payment_ids' => ['pay_conflict']],
            ['extra_payment_ids' => [null, false, 0, '']],
            ['conflicting_payment_ids' => [false]],
            ['wrapper' => ['extra_payment_ids' => ['']]],
            ['nested' => ['inner' => ['note' => 'held']]],
            ['nested' => ['count' => 2]],
            ['flag' => true],
            ['items' => [false, 0, 'held']],
            (object) ['note' => 'held'],
        ];

        foreach ($evidence as $value) {
            $this->assertTrue(Order::reconciliationValueHasEvidence($value));
        }
    }
}
