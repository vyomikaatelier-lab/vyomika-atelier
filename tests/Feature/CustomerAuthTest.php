<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_customer_cannot_login_with_correct_credentials(): void
    {
        $user = User::factory()->disabled()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_verified_customer_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }
}
