<?php

namespace App\Services;

use App\Services\RefundMoney;
use App\Support\IndiaDelivery;
use App\Support\ProductFulfilment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChargeConfirmationEnquiry
{
    public function __construct(private LeadProtectionService $leadProtection) {}

    /**
     * Save a quotation enquiry without creating an order or contacting the gateway.
     * The cart is left in place. Notification mail failure does not undo the lead.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $validatedAddress
     * @param  array<string, mixed>  $quote
     */
    public function store(Request $request, Collection $items, array $validatedAddress, array $quote): RedirectResponse
    {
        $request->merge([
            'name' => $validatedAddress['full_name'],
            'email' => $validatedAddress['email'],
            'phone' => $validatedAddress['phone_normalized'],
        ]);

        if ($response = $this->leadProtection->guard($request, InternationalShippingEnquiry::FORM_KEY)) {
            return $response;
        }

        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'product_id' => $item['product']->id,
                'name' => $item['product']->name,
                'quantity' => (int) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'size_label' => $item['size_label'] ?? null,
                'finish_name' => $item['finish_name'] ?? null,
            ];
        }

        $reasons = array_values($quote['reasons'] ?? []);
        $result = $this->leadProtection->finalizeLead($request, InternationalShippingEnquiry::FORM_KEY, [
            'name' => $validatedAddress['full_name'],
            'email' => $validatedAddress['email'],
            'phone' => $validatedAddress['phone_normalized'],
            'type' => 'fulfilment_quote',
            'subject' => 'Charge confirmation enquiry',
            'message' => $this->message($reasons, $lines),
            'status' => 'new',
            'expected_order_value' => RefundMoney::formatRupees((int) ($quote['merchandise_paise'] ?? 0)),
            'metadata' => [
                'fulfilment_quote' => true,
                'payment_started' => false,
                'confirmation_reasons' => $reasons,
                'lines' => $lines,
                'merchandise_subtotal_paise' => (int) ($quote['merchandise_paise'] ?? 0),
            ],
        ]);

        $lead = $result['lead'];
        if ($result['notify']) {
            $this->leadProtection->notifyAdmin(
                $lead,
                $lead->message,
                'Charge confirmation enquiry — Vyomika Atelier'
            );
        }

        return redirect()
            ->route('checkout.index')
            ->with('success', $lead->duplicate_of_id ? $result['success_message'] : ProductFulfilment::QUOTE_SAVED)
            ->with('enquiry_receipt', [
                'lines' => collect($lines)->map(fn (array $line) => [
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                ])->all(),
                'destination' => IndiaDelivery::isIndia($validatedAddress['country_resolved'] ?? null)
                    ? 'India'
                    : (string) ($validatedAddress['country_resolved'] ?? ''),
                'reasons' => $reasons,
            ]);
    }

    /**
     * @param  list<string>  $reasons
     * @param  list<array<string, mixed>>  $lines
     */
    private function message(array $reasons, array $lines): string
    {
        $productLines = collect($lines)->map(function (array $line) {
            return $line['name'].' × '.$line['quantity'];
        })->implode("\n");

        return implode("\n", [
            'Charge confirmation enquiry. No online payment was taken.',
            '',
            'Charges that need confirmation:',
            $reasons === [] ? 'None listed.' : implode("\n", $reasons),
            '',
            'Products:',
            $productLines,
        ]);
    }
}
