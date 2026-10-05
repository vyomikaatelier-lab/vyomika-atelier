<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductFulfilment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamageRefundPolicyCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_defaults_state_the_conditional_damage_policy_once(): void
    {
        $config = config('legal.pages');
        $json = json_decode((string) file_get_contents(public_path('data/legal.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([$config, $json['pages']] as $pages) {
            $shipping = $this->paragraphs($pages['shipping']['sections']);
            $cancellation = $this->paragraphs($pages['cancellation']['sections']);
            $warranty = $this->paragraphs($pages['warranty']['sections']);
            $terms = $this->paragraphs($pages['terms']['sections']);

            $this->assertStringContainsString('continuous video of delivery and unpacking', $shipping);
            $this->assertStringContainsString('does not by itself end a valid claim under applicable law', $shipping);
            $this->assertStringContainsString('Shipping within India is included in the displayed product price.', $shipping);
            $this->assertStringContainsString('routine return for a change of mind', $cancellation);
            $this->assertStringContainsString('ready-stock order', $cancellation);
            $this->assertStringContainsString('Glass is not excluded as a category.', $warranty);
            $this->assertStringContainsString('customer-appointed installer', $warranty);
            $this->assertStringContainsString('do not treat the customer as responsible by default', $warranty);
            $this->assertStringContainsString('is not a condition of it', $warranty);
            $this->assertStringContainsString('This review is not a guarantee of damage-free delivery, transit insurance, or a compensation payment.', $warranty);
            $this->assertStringContainsString('as stated on its page', $terms);

            foreach ([$shipping, $cancellation, $warranty, $terms] as $text) {
                $this->assertStringNotContainsString('we do not accept returns', strtolower($text));
                $this->assertStringNotContainsString('no returns on bespoke', strtolower($text));
                $this->assertStringNotContainsString('all items are made to order unless', strtolower($text));
                $this->assertStringNotContainsString('site installation errors by third parties', strtolower($text));
                $this->assertStringNotContainsString('guaranteed compensation', strtolower($text));
            }
        }
    }

    public function test_published_fallbacks_and_product_pages_do_not_bar_a_statutory_claim(): void
    {
        $warranty = $this->get(route('legal.warranty'));
        $warranty->assertOk();
        $warranty->assertSee('Glass is not excluded as a category.', false);
        $warranty->assertSee('customer-appointed installer', false);
        $warranty->assertSee('Compensation a carrier pays us is separate', false);
        $warranty->assertSee('This review is not a guarantee of damage-free delivery, transit insurance, or a compensation payment.', false);
        $warranty->assertDontSee('we do not accept returns', false);
        $warranty->assertDontSee('No Returns on Custom Products', false);

        $cancellation = $this->get(route('legal.cancellation'));
        $cancellation->assertOk();
        $cancellation->assertSee('routine return for a change of mind', false);
        $cancellation->assertSee('does not limit a remedy for damage', false);
        $cancellation->assertDontSee('All Vyomika Atelier products are made to your specifications', false);

        $shipping = $this->get(route('legal.shipping'));
        $shipping->assertOk();
        $shipping->assertSee('A missing video or a report after 48 hours does not by itself end a valid claim under applicable law.', false);
        $shipping->assertSee('Shipping within India is included in the displayed product price.', false);

        $category = Category::factory()->create([
            'slug' => 'policy-mirrors',
            'section' => 'shop',
            'is_active' => true,
        ]);
        $ready = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'policy-ready-mirror',
            'is_active' => true,
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'tab_shipping' => null,
            'tab_packaging' => null,
        ]);
        $made = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'policy-made-mirror',
            'is_active' => true,
            'availability_mode' => ProductFulfilment::AVAILABILITY_MADE,
            'tab_shipping' => null,
            'tab_packaging' => null,
        ]);

        $readyPage = $this->get(route('shop.show', $ready->slug));
        $readyPage->assertOk();
        $readyPage->assertSee('continuous video of delivery and unpacking', false);
        $readyPage->assertSee('does not by itself end a valid claim under applicable law', false);
        $readyPage->assertDontSee('no returns on bespoke metalwork', false);
        $readyPage->assertDontSee('change-of-mind return', false);

        $madePage = $this->get(route('shop.show', $made->slug));
        $madePage->assertOk();
        $madePage->assertSee('routine change-of-mind return', false);
        $madePage->assertDontSee('no returns on bespoke metalwork', false);
    }

    /** @param list<array{heading: string, paragraphs: list<string>}> $sections */
    private function paragraphs(array $sections): string
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => $section['paragraphs'] ?? [])
            ->implode("\n");
    }
}
