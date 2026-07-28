<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The move up/down buttons compare sort_order with a strict inequality, so any
 * rows sharing a value (the seeded default is 0) had no neighbour and the click
 * silently did nothing. These tests pin the visible ordering instead.
 */
class AdminReorderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return list<string> */
    private function categoryOrder(string $section): array
    {
        return Category::query()
            ->where('section', $section)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function seedTiedCategories(): void
    {
        Category::query()->delete();

        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            Category::query()->create([
                'name' => $name,
                'slug' => strtolower($name),
                'section' => 'shop',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }
    }

    public function test_move_down_reorders_categories_that_share_a_sort_order(): void
    {
        $this->seedTiedCategories();
        $alpha = Category::query()->where('slug', 'alpha')->firstOrFail();

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->categoryOrder('shop'));

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.categories.move', [$alpha, 'down']))
            ->assertRedirect();

        $this->assertSame(['Bravo', 'Alpha', 'Charlie'], $this->categoryOrder('shop'));
    }

    public function test_move_up_reorders_categories_that_share_a_sort_order(): void
    {
        $this->seedTiedCategories();
        $charlie = Category::query()->where('slug', 'charlie')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.categories.move', [$charlie, 'up']))
            ->assertRedirect();

        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->categoryOrder('shop'));
    }

    public function test_move_up_on_the_first_category_leaves_the_order_alone(): void
    {
        $this->seedTiedCategories();
        $alpha = Category::query()->where('slug', 'alpha')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.categories.move', [$alpha, 'up']))
            ->assertRedirect();

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->categoryOrder('shop'));
    }

    public function test_moving_an_active_category_ignores_hidden_inactive_rows(): void
    {
        Category::query()->delete();

        Category::query()->create([
            'name' => 'Archived Decor',
            'slug' => 'archived-decor',
            'section' => 'shop',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        foreach (['Mirrors', 'Tables'] as $index => $name) {
            Category::query()->create([
                'name' => $name,
                'slug' => strtolower($name),
                'section' => 'shop',
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $mirrors = Category::query()->where('slug', 'mirrors')->firstOrFail();
        $archived = Category::query()->where('slug', 'archived-decor')->firstOrFail();

        // Default admin listing shows active rows only, so Mirrors is the first
        // visible row and moving it up must not disturb the archived category.
        $this->actingAsAdmin($this->admin())
            ->post(route('admin.categories.move', [$mirrors, 'up']), ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(
            ['Mirrors', 'Tables'],
            Category::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->pluck('name')->all()
        );

        $this->assertTrue(
            $archived->fresh()->sort_order < $mirrors->fresh()->sort_order,
            'a hidden inactive category was reshuffled by a move in the active list'
        );
    }

    public function test_move_down_reorders_exhibitions_that_share_a_sort_order(): void
    {
        foreach (['India Design ID', 'Paris Design Week'] as $name) {
            Exhibition::query()->create([
                'name' => $name,
                'slug' => str($name)->slug()->value(),
                'year' => 2026,
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }

        $first = Exhibition::query()->where('slug', 'india-design-id')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.exhibitions.move', [$first, 'down']))
            ->assertRedirect();

        $this->assertSame(
            ['Paris Design Week', 'India Design ID'],
            Exhibition::query()->orderBy('sort_order')->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_reorder_endpoint_assigns_sequential_positions(): void
    {
        $this->seedTiedCategories();

        $ids = Category::query()->orderBy('name')->pluck('id')->all();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.categories.reorder'), ['order' => array_reverse($ids)])
            ->assertRedirect();

        $this->assertSame(
            ['Charlie', 'Bravo', 'Alpha'],
            Category::query()->orderBy('sort_order')->pluck('name')->all()
        );
    }
}
