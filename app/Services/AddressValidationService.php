<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddressValidationService
{
    /**
     * @return array<string, mixed>
     */
    public function validate(array $input, bool $isCheckout = false): array
    {
        $country = $this->resolveCountry($input);
        $isIndia = $country === 'India';

        $rules = [
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'phone' => 'required|string|max:20',
            'alt_mobile' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'country' => ['required', 'string', Rule::in(config('addresses.countries', []))],
            'country_other' => 'nullable|required_if:country,Other|string|max:100',
            'house_building' => 'required|string|max:120',
            'street' => 'nullable|string|max:200',
            'locality' => 'nullable|string|max:120',
            'landmark' => 'nullable|string|max:120',
            'city' => 'required|string|max:100',
            'state' => $isIndia ? ['required', 'string', Rule::in(config('addresses.india_states', []))] : 'required|string|max:100',
            'pincode' => $isIndia ? 'required|string|regex:/^[1-9][0-9]{5}$/' : 'required|string|max:20',
            'address_type' => ['nullable', 'string', Rule::in(array_keys(config('addresses.address_types', [])))],
            'floor' => 'nullable|string|max:30',
            'lift_available' => 'nullable|boolean',
            'delivery_instructions' => 'nullable|string|max:500',
            'billing_same_as_shipping' => 'nullable|boolean',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($isCheckout) {
            $rules['customer_name'] = 'nullable|string|max:255';
            $rules['customer_email'] = 'sometimes|email|max:255';
            $rules['customer_phone'] = 'sometimes|string|max:20';
            $rules['shipping_address'] = 'sometimes|string|max:500';
            $rules['payment_method'] = 'sometimes|in:razorpay';
        } else {
            $rules['label'] = 'required|string|max:60';
            $rules['name'] = 'nullable|string|max:120';
            $rules['address_line1'] = 'sometimes|string|max:255';
            $rules['is_default'] = 'nullable|boolean';
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['country_resolved'] = $country;
        $validated['full_name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        $validated['phone_normalized'] = $this->normalizePhone($validated['phone']);
        $validated['alt_mobile_normalized'] = filled($validated['alt_mobile'] ?? null)
            ? $this->normalizePhone($validated['alt_mobile'])
            : null;
        $validated['pincode_normalized'] = $isIndia
            ? preg_replace('/\D/', '', $validated['pincode'])
            : trim($validated['pincode']);
        $validated['pin_lookup_status'] = $this->pinLookupStatus($country, $validated['pincode_normalized']);
        $validated['address_type'] = $validated['address_type'] ?? 'home';
        $validated['billing_same_as_shipping'] = (bool) ($validated['billing_same_as_shipping'] ?? true);

        return $validated;
    }

    public function resolveCountry(array $input): string
    {
        $country = $input['country'] ?? 'India';

        if ($country === 'Other') {
            return trim((string) ($input['country_other'] ?? '')) ?: 'Other';
        }

        return $country;
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    public function pinLookupStatus(string $country, string $pincode): string
    {
        if ($country !== 'India') {
            return 'format_valid';
        }

        if (preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            return 'format_valid';
        }

        return 'manual_review';
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function toSnapshot(array $validated): array
    {
        $streetLine = $this->formatStreetLine($validated);

        return [
            'full_name' => $validated['full_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone_normalized'],
            'alt_mobile' => $validated['alt_mobile_normalized'],
            'email' => $validated['email'],
            'country' => $validated['country_resolved'],
            'house_building' => $validated['house_building'],
            'street' => $validated['street'] ?? null,
            'locality' => $validated['locality'] ?? null,
            'landmark' => $validated['landmark'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode_normalized'],
            'pin_lookup_status' => $validated['pin_lookup_status'],
            'address_type' => $validated['address_type'],
            'floor' => $validated['floor'] ?? null,
            'lift_available' => isset($validated['lift_available']) ? (bool) $validated['lift_available'] : null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'company' => $validated['company'] ?? null,
            'billing_same_as_shipping' => $validated['billing_same_as_shipping'],
            'formatted_line' => $streetLine,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function formatStreetLine(array $validated): string
    {
        $parts = array_filter([
            $validated['house_building'] ?? null,
            $validated['street'] ?? null,
            $validated['locality'] ?? null,
            filled($validated['landmark'] ?? null) ? 'Near ' . $validated['landmark'] : null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Map checkout form field names to validation input.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function mapCheckoutInput(array $request): array
    {
        $mapped = $request;

        if (filled($request['customer_phone'] ?? null) && empty($mapped['phone'])) {
            $mapped['phone'] = $request['customer_phone'];
        }

        if (filled($request['customer_email'] ?? null) && empty($mapped['email'])) {
            $mapped['email'] = $request['customer_email'];
        }

        if (filled($request['shipping_address'] ?? null) && empty($mapped['house_building'])) {
            $mapped['house_building'] = $request['shipping_address'];
        }

        if (filled($request['first_name'] ?? null) || filled($request['last_name'] ?? null)) {
            $mapped['first_name'] = $request['first_name'] ?? '';
            $mapped['last_name'] = $request['last_name'] ?? '';
        } elseif (filled($request['customer_name'] ?? null)) {
            $parts = explode(' ', trim($request['customer_name']), 2);
            $mapped['first_name'] = $parts[0] ?? '';
            $mapped['last_name'] = $parts[1] ?? '';
        }

        return $mapped;
    }
}
