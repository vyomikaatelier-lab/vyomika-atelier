<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_flash_notification_markup_and_css_stay_inside_the_viewport(): void
    {
        $css = (string) file_get_contents(public_path('css/amerce.css'));

        $this->assertStringContainsString('.am-flash', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.am-flash[^{]*\{[^}]*translateX\(-50%\)/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.am-flash[^{]*\{[^}]*left:\s*50%/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.am-alert\s*\{[^}]*left:\s*50%;[^}]*transform:\s*translateX\(-50%\)/s',
            $css
        );

        preg_match_all('/\.am-flash[^{]*\{[^}]+\}/s', $css, $flashBlocks);
        $flashCss = implode("\n", $flashBlocks[0] ?? []);
        $this->assertStringContainsString('right:', $flashCss);
        $this->assertStringNotContainsString('left: 0', $flashCss);
        $this->assertStringContainsString('12px', $css);

        $this->withSession(['success' => 'Added to cart.'])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('am-flash', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-label="Dismiss notification"', false)
            ->assertSee('Added to cart.', false);

        $this->withSession(['error' => 'Your cart is empty.'])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('Your cart is empty.', false);

        $this->withSession(['info' => 'Please sign in to complete your purchase.'])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Please sign in to complete your purchase.', false);
    }
}
