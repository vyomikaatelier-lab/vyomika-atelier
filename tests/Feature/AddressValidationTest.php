<?php

namespace Tests\Feature;

use App\Services\AddressValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AddressValidationTest extends TestCase
{
    use RefreshDatabase;

    private AddressValidationService $addresses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addresses = app(AddressValidationService::class);
    }

    private function validIndiaPayload(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '9876543210',
            'email' => 'jane@example.com',
            'country' => 'India',
            'house_building' => 'Flat 12, Tower A',
            'street' => 'Linking Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
        ];
    }

    public function test_india_address_requires_valid_pincode(): void
    {
        $payload = $this->validIndiaPayload();
        $payload['pincode'] = 'ABC';

        $this->expectException(ValidationException::class);
        $this->addresses->validate($payload);
    }

    public function test_india_address_requires_valid_state(): void
    {
        $payload = $this->validIndiaPayload();
        $payload['state'] = 'Not A Real State';

        $this->expectException(ValidationException::class);
        $this->addresses->validate($payload);
    }

    public function test_international_address_does_not_force_indian_pin(): void
    {
        $payload = $this->validIndiaPayload();
        $payload['country'] = 'United States';
        $payload['state'] = 'California';
        $payload['pincode'] = '90210';

        $validated = $this->addresses->validate($payload);

        $this->assertSame('90210', $validated['pincode_normalized']);
        $this->assertSame('format_valid', $validated['pin_lookup_status']);
    }

    public function test_snapshot_is_immutable_structure(): void
    {
        $validated = $this->addresses->validate($this->validIndiaPayload());
        $snapshot = $this->addresses->toSnapshot($validated);

        $this->assertSame('Jane Doe', $snapshot['full_name']);
        $this->assertSame('400001', $snapshot['pincode']);
        $this->assertArrayHasKey('formatted_line', $snapshot);
    }
}
