<?php

namespace App\Services;

use App\Support\IndiaDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InternationalShippingEnquiry
{
    public const FORM_KEY = 'international_shipping';

    public function __construct(private LeadProtectionService $leadProtection) {}

    /**
     * Persist a shipping enquiry from the server cart and the validated destination.
     * Does not create an order, reserve stock, or contact the payment gateway.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $validatedAddress
     */
    public function store(Request $request, Collection $items, array $validatedAddress): RedirectResponse
    {
        $request->merge([
            'name' => $validatedAddress['full_name'],
            'email' => $validatedAddress['email'],
            'phone' => $validatedAddress['phone_normalized'],
        ]);

        if ($response = $this->leadProtection->guard($request, self::FORM_KEY)) {
            return $response;
        }

        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = (float) $item['line_total'];
            $subtotal += $lineTotal;
            $lines[] = [
                'product_id' => $item['product']->id,
                'name' => $item['product']->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'size_label' => $item['size_label'] ?? null,
                'finish_name' => $item['finish_name'] ?? null,
            ];
        }

        $destination = [
            'country' => $validatedAddress['country_resolved'],
            'city' => $validatedAddress['city'],
            'state' => $validatedAddress['state'] ?? null,
            'pincode' => $validatedAddress['pincode_normalized'],
            'address' => $validatedAddress['house_building'] ?? null,
            'street' => $validatedAddress['street'] ?? null,
            'locality' => $validatedAddress['locality'] ?? null,
        ];

        $result = $this->leadProtection->finalizeLead($request, self::FORM_KEY, [
            'name' => $validatedAddress['full_name'],
            'email' => $validatedAddress['email'],
            'phone' => $validatedAddress['phone_normalized'],
            'type' => 'international_shipping',
            'subject' => 'International shipping enquiry',
            'message' => $this->message($destination, $lines, $subtotal),
            'status' => 'new',
            'expected_order_value' => $subtotal,
            'metadata' => [
                'international_shipping_enquiry' => true,
                'payment_started' => false,
                'destination' => $destination,
                'lines' => $lines,
                'merchandise_subtotal' => $subtotal,
            ],
        ]);

        $lead = $result['lead'];

        if ($result['notify']) {
            $this->leadProtection->notifyAdmin(
                $lead,
                $lead->message,
                'International shipping enquiry — Vyomika Atelier'
            );
        }

        $confirmation = $lead->duplicate_of_id
            ? $result['success_message']
            : IndiaDelivery::ENQUIRY_SAVED;

        return redirect()
            ->route('checkout.index')
            ->with('success', $confirmation)
            ->with('enquiry_receipt', [
                'lines' => collect($lines)->map(fn (array $line) => [
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                ])->all(),
                'destination' => collect([
                    $destination['city'] ?? null,
                    $destination['country'] ?? null,
                ])->filter()->implode(', '),
                'subtotal' => $subtotal,
            ]);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @param  list<array<string, mixed>>  $lines
     */
    private function message(array $destination, array $lines, float $subtotal): string
    {
        $productLines = collect($lines)->map(function (array $line) {
            $variant = collect([$line['size_label'] ?? null, $line['finish_name'] ?? null])
                ->filter()
                ->implode(', ');
            $label = $line['name'].($variant !== '' ? ' ('.$variant.')' : '');

            return $label.' × '.$line['quantity'];
        })->implode("\n");

        $address = collect([
            $destination['address'] ?? null,
            $destination['street'] ?? null,
            $destination['locality'] ?? null,
            $destination['city'] ?? null,
            $destination['state'] ?? null,
            $destination['pincode'] ?? null,
            $destination['country'] ?? null,
        ])->filter()->implode(', ');

        return implode("\n", [
            'International shipping enquiry. No online payment was taken.',
            'Merchandise subtotal (shipping not included): ₹'.number_format($subtotal, 2, '.', ''),
            '',
            'Products:',
            $productLines,
            '',
            'Destination:',
            $address,
            '',
            'Our team will confirm shipping charges, estimated delivery time, and import-duty responsibility before payment.',
        ]);
    }
}
