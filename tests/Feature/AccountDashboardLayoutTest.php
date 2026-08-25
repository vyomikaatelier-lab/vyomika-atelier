<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function layoutCss(): string
    {
        return (string) file_get_contents(public_path('css/amerce.css'))
            .(string) file_get_contents(public_path('css/responsive.css'));
    }

    private function firstRuleBlock(string $css, string $selector): string
    {
        $quoted = preg_quote($selector, '/');
        if (! preg_match('/'.$quoted.'[^{]*\{([^}]+)\}/', $css, $matches)) {
            return '';
        }

        return $matches[1];
    }

    private function mediaQueryBody(string $css, string $query): string
    {
        $needle = '@media ('.$query.')';
        $offset = 0;

        while (($start = strpos($css, $needle, $offset)) !== false) {
            $brace = strpos($css, '{', $start);
            if ($brace === false) {
                return '';
            }

            $depth = 0;
            $len = strlen($css);
            for ($i = $brace; $i < $len; $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } elseif ($css[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $body = substr($css, $brace + 1, $i - $brace - 1);
                        if (str_contains($body, '.am-account-dashboard') || str_contains($body, '.am-account-shell')) {
                            return $body;
                        }
                        $offset = $i + 1;
                        break;
                    }
                }
            }

            if ($offset <= $start) {
                break;
            }
        }

        return '';
    }

    public function test_dedicated_account_container_exists_without_global_cap(): void
    {
        $css = $this->layoutCss();
        $containerRule = $this->firstRuleBlock($css, '.am-container');
        $shellRule = $this->firstRuleBlock($css, '.am-account-shell');

        $this->assertStringContainsString('max-width: none', $containerRule);
        $this->assertStringContainsString('.am-account-shell', $css);
        $this->assertStringContainsString('width: 100%', $shellRule);
        $this->assertStringContainsString('margin-inline: auto', $shellRule);
        $this->assertStringContainsString('padding-inline: var(--site-gutter, var(--am-gutter))', $shellRule);
        $this->assertMatchesRegularExpression('/max-width:\s*(11[89]\d|12[0-3]\d|1240)px/', $shellRule);

        $this->assertStringNotContainsString('--site-content-max:', $css);
        $this->assertStringNotContainsString('repeat(auto-fill, minmax(min(100%, 20rem), 1fr))', $css);
    }

    public function test_desktop_account_grid_exists(): void
    {
        $css = $this->layoutCss();
        $desktop = $this->mediaQueryBody($css, 'min-width: 1024px');

        $this->assertNotSame('', $desktop, 'Expected a 1024px account layout media query');
        $this->assertMatchesRegularExpression(
            '/\.am-account-dashboard__layout\s*\{[^}]*grid-template-columns:\s*(2[89]\dpx|3[0-1]\dpx|320px)\s+minmax\(0,\s*1fr\)/',
            $desktop
        );
        $this->assertStringContainsString('.am-account-dashboard__sidebar', $css);
        $this->assertStringContainsString('.am-account-dashboard__main', $css);
        $this->assertStringContainsString('.am-account-dashboard__summary', $css);
    }

    public function test_responsive_single_column_rule_exists(): void
    {
        $css = $this->layoutCss();
        $layoutRule = $this->firstRuleBlock($css, '.am-account-dashboard__layout');
        $stacked = $this->mediaQueryBody($css, 'max-width: 1023px');
        $mobile = $this->mediaQueryBody($css, 'max-width: 639px');

        $this->assertStringContainsString('grid-template-columns: 1fr', $layoutRule);
        $this->assertStringContainsString('.am-account-dashboard__layout', $stacked);
        $this->assertStringContainsString('grid-template-columns: 1fr', $stacked);
        $this->assertStringContainsString('.am-address-form__grid', $mobile);
        $this->assertStringContainsString('grid-template-columns: 1fr', $mobile);
        $this->assertStringContainsString('@media (max-width: 720px)', $css);
    }

    public function test_account_dashboard_uses_shell_and_preserves_forms(): void
    {
        $user = User::factory()->create([
            'name' => 'Hitesh Kumar',
            'email' => 'hitesh.layout@example.com',
            'mobile_country_code' => '+91',
            'mobile' => '9818891878',
        ]);

        $address = $user->addresses()->create([
            'label' => 'Home',
            'name' => 'Hitesh Kumar',
            'phone' => '9818891878',
            'address_line1' => '12 Studio Lane',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'is_default' => true,
        ]);

        $html = $this->actingAs($user)
            ->withSession(['success' => 'Welcome to your account.'])
            ->get(route('account'))
            ->assertOk()
            ->assertSee('am-account-shell', false)
            ->assertSee('am-account-dashboard__layout', false)
            ->assertSee('am-account-dashboard__sidebar', false)
            ->assertSee('am-account-dashboard__main', false)
            ->assertSee('am-account-dashboard__summary', false)
            ->assertSee('am-page-hero--account', false)
            ->assertSee('Welcome to your account.')
            ->assertSee('Save Profile')
            ->assertSee('Update Address')
            ->assertSee('View Cart')
            ->assertSee('Custom Order')
            ->assertSee('Logout')
            ->assertSee('Mobile: +91 9818891878 (verified)')
            ->assertSee('Enquiries')
            ->assertSee('Quotation Requests')
            ->assertSee('Orders')
            ->assertSee('Professional Application')
            ->assertDontSee('Send OTP')
            ->assertDontSee('Verify OTP')
            ->getContent();

        $this->assertStringContainsString('action="'.route('account.profile.update').'"', $html);
        $this->assertStringContainsString('action="'.route('account.addresses.store').'"', $html);
        $this->assertStringContainsString('action="'.route('account.logout').'"', $html);
        $this->assertStringContainsString('action="'.route('account.addresses.destroy', $address).'"', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'method="POST"'));
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="DELETE"', $html);
        $this->assertLessThan(
            strpos($html, 'am-account-dashboard__layout'),
            strpos($html, 'am-account-notice')
        );

        foreach ([
            'name', 'email', 'whatsapp', 'city', 'label', 'first_name', 'last_name',
            'phone', 'alt_mobile', 'company', 'country', 'house_building', 'street',
            'locality', 'landmark', 'pincode', 'address_type', 'floor', 'lift_available',
            'delivery_instructions', 'billing_same_as_shipping', 'is_default',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "Missing input name [{$field}]");
        }
    }

    public function test_profile_update_and_logout_routes_remain_post(): void
    {
        $user = User::factory()->create([
            'name' => 'Hitesh Kumar',
            'email' => 'hitesh.layout@example.com',
            'city' => 'Mumbai',
        ]);

        $this->actingAs($user)
            ->from(route('account'))
            ->post(route('account.profile.update'), [
                'name' => 'Hitesh Atelier',
                'email' => 'hitesh.layout@example.com',
                'whatsapp' => '9818891878',
                'city' => 'Delhi',
            ])
            ->assertRedirect(route('account'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Hitesh Atelier',
            'city' => 'Delhi',
        ]);

        $this->actingAs($user->fresh())
            ->post(route('account.logout'))
            ->assertRedirect();

        $this->assertGuest();
    }
}
