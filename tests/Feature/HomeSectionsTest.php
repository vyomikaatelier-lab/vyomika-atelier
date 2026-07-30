<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\HomeSections;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_shop_by_collection_row_with_four_cards(): void
    {
        $this->seedShopCategories();

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('id="shop-by-collection"', false);
        $response->assertSee(config('site.collection_row.title'), false);
        $response->assertSee('am-collection-card', false);

        $cards = HomeSections::collectionCards();
        $this->assertCount(4, $cards);

        foreach ($cards as $card) {
            $response->assertSee($card['label'], false);
            $response->assertSee($card['url'], false);
        }
    }

    public function test_collection_cards_link_to_shop_category_pages_from_the_database(): void
    {
        $this->seedShopCategories();

        Category::query()->where('slug', 'coffee-tables')->update([
            'name' => 'Coffee Tables (Renamed)',
            'sort_order' => 0,
        ]);

        $cards = collect(HomeSections::collectionCards(6))->keyBy('slug');

        $this->assertSame('Coffee Tables (Renamed)', $cards['coffee-tables']['label']);
        $this->assertSame(route('shop.show', 'coffee-tables'), $cards['coffee-tables']['url']);
        $this->assertSame(route('shop.mirror-frames.index'), $cards['mirror-frames']['url']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Coffee Tables (Renamed)', false);
    }

    public function test_deactivated_categories_are_left_out_of_the_collection_row(): void
    {
        $this->seedShopCategories();

        Category::query()->where('slug', 'door-handles')->update(['is_active' => false]);

        $slugs = collect(HomeSections::collectionCards(6))->pluck('slug')->all();

        $this->assertNotContains('door-handles', $slugs);
    }

    public function test_collection_card_prefers_category_image_then_config_artwork(): void
    {
        $this->seedShopCategories();

        Category::query()->where('slug', 'coffee-tables')->update([
            'image' => 'https://cdn.example.com/coffee-tables.jpg',
        ]);

        $cards = collect(HomeSections::collectionCards(6))->keyBy('slug');

        $this->assertSame('https://cdn.example.com/coffee-tables.jpg', $cards['coffee-tables']['image']);
        $this->assertSame(
            config('site.collection_row.cards.door-handles.image'),
            $cards['door-handles']['image']
        );
    }

    public function test_collection_card_falls_back_to_a_product_photo(): void
    {
        $this->seedShopCategories();

        config(['site.collection_row.cards.door-handles.image' => null]);

        $category = Category::query()->where('slug', 'door-handles')->firstOrFail();

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Brass Pull Handle',
            'slug' => 'brass-pull-handle',
            'description' => 'Card image fallback.',
            'price' => 2400,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'image' => 'https://cdn.example.com/brass-pull-handle.jpg',
        ]);

        $cards = collect(HomeSections::collectionCards(6))->keyBy('slug');

        $this->assertSame('https://cdn.example.com/brass-pull-handle.jpg', $cards['door-handles']['image']);
    }

    public function test_homepage_renders_four_numbered_how_it_works_steps(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('id="how-it-works"', false);
        $response->assertSee(config('site.how_it_works.title'));
        $response->assertSee('am-step__number', false);

        $steps = config('site.how_it_works.steps');
        $this->assertCount(4, $steps);

        foreach ($steps as $step) {
            $response->assertSee($step['title']);
            $response->assertSee($step['description']);
        }
    }

    public function test_homepage_renders_quote_cta_linking_to_enquiry_pages(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('id="get-a-quote"', false);
        $response->assertSee('am-quote-cta__body', false);
        $response->assertSee(config('site.quote_cta.title'));
        $response->assertSee(url(config('site.quote_cta.primary_cta_href')), false);
        $response->assertSee(url(config('site.quote_cta.secondary_cta_href')), false);

        foreach (config('site.quote_cta.points') as $point) {
            $response->assertSee($point);
        }
    }

    public function test_homepage_renders_three_review_cards_with_stars_and_verified_badge(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('id="reviews"', false);
        $response->assertSee(config('site.reviews.title'));
        $response->assertSee('Verified buyer', false);
        $response->assertSee('★★★★★', false);

        $reviews = HomeSections::reviews();
        $this->assertCount(3, $reviews['cards']);

        foreach ($reviews['cards'] as $review) {
            $response->assertSee($review['client'], false);
            $this->assertTrue($review['verified']);
            $this->assertSame(5, $review['rating']);
        }
    }

    public function test_review_rating_is_clamped_and_defaults_to_five_stars(): void
    {
        config(['site.testimonials' => [
            ['quote' => 'No rating saved.', 'client' => 'Anon', 'role' => 'Homeowner'],
            ['quote' => 'Rating out of range.', 'client' => 'Anon Two', 'role' => 'Homeowner', 'rating' => 9],
            ['quote' => 'Low rating.', 'client' => 'Anon Three', 'role' => 'Homeowner', 'rating' => 3],
        ]]);

        $cards = HomeSections::reviews()['cards'];

        $this->assertSame([5, 5, 3], array_column($cards, 'rating'));
        $this->assertFalse($cards[0]['verified']);
    }

    public function test_homepage_keeps_hero_and_announcement_bar(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('am-hero__slides', false);
        $response->assertSee(config('site.announcement.text'), false);
        $response->assertSee(config('site.hero.slides.0.title'), false);
    }

    public function test_footer_renders_shop_studio_trade_and_legal_columns(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('<h5>Shop</h5>', false);
        $response->assertSee('<h5>Studio</h5>', false);
        $response->assertSee('<h5>Trade</h5>', false);
        $response->assertSee('<h5>Legal</h5>', false);

        foreach (config('site.footer.trade_links') as $link) {
            $response->assertSee(route($link['route'], $link['params'] ?? []), false);
            $response->assertSee($link['label']);
        }
    }

    private function seedShopCategories(): void
    {
        foreach (StorefrontRoutes::shopCategorySlugs() as $index => $slug) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => StorefrontRoutes::shopCategoryLabel($slug),
                    'section' => 'shop',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
