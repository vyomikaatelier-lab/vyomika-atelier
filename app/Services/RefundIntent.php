<?php

namespace App\Services;

final class RefundIntent
{
    /**
     * @param  array<int, int>  $lineQuantities
     */
    public function __construct(
        public string $idempotencyKey,
        public string $kind,
        public string $reasonCode,
        public ?string $internalNote,
        public ?string $orderNumberConfirmation,
        public bool $includeShipping,
        public array $lineQuantities,
    ) {}
}
