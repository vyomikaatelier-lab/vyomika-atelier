<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StaffIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StaffIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_a_stable_internal_staff_id_to_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(StaffIdentityService::class);

        $staffId = $service->ensure($admin);

        $this->assertSame(sprintf('VA-STF-%06d', $admin->id), $staffId);
        $this->assertSame($staffId, $admin->fresh()->staff_id);
        $this->assertSame($staffId, $service->ensure($admin));
    }

    public function test_it_never_assigns_a_staff_id_to_a_customer(): void
    {
        $customer = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(StaffIdentityService::class)->ensure($customer);
    }
}
