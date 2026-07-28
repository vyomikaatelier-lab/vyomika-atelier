<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHP silently discards form fields past max_input_vars (default 1000), so an
 * oversized admin form appears to save while the tail of the payload is gone.
 * These tests pin the field count of the biggest forms so the budget cannot be
 * exceeded without someone noticing.
 */
class AdminFormInputBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Headroom under the conservative shared-hosting default of 1000. Nested
     * array inputs (a[b][c]) each count once, which is what this measures.
     */
    private const BUDGET = 700;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function countInputs(string $html): int
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname\s*=\s*"([^"]+)"/i', $html, $matches);

        return count($matches[1]);
    }

    public function test_admin_form_stays_within_the_input_variable_budget(): void
    {
        $paths = [
            'admin/independent-pages/railings/edit',
            'admin/independent-pages/corten-steel/edit',
            'admin/settings',
            'admin/page-heroes/about/edit',
        ];

        $admin = $this->admin();

        foreach ($paths as $path) {
            $response = $this->actingAsAdmin($admin)->get('/'.$path);
            $response->assertOk();

            $count = $this->countInputs($response->getContent());

            $this->assertGreaterThan(0, $count, $path.' rendered no form inputs');
            $this->assertLessThanOrEqual(
                self::BUDGET,
                $count,
                $path.' posts '.$count.' fields, over the '.self::BUDGET
                    .' budget; PHP max_input_vars would truncate the payload.'
            );
        }
    }

    public function test_product_form_stays_within_the_input_variable_budget(): void
    {
        $product = Product::query()->create([
            'name' => 'Knurled Lever Handle',
            'slug' => 'knurled-lever-handle',
            'price' => 4500,
            'is_active' => true,
        ]);

        $response = $this->actingAsAdmin($this->admin())
            ->get(route('admin.products.edit', $product));
        $response->assertOk();

        $count = $this->countInputs($response->getContent());

        $this->assertGreaterThan(0, $count);
        $this->assertLessThanOrEqual(self::BUDGET, $count, 'product form posts '.$count.' fields');
    }
}
