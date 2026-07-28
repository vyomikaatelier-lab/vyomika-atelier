<?php

namespace Tests\Feature;

use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExhibitionEmptyFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_empty_year_and_sort_order_do_not_block_save(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->post(route('admin.exhibitions.store'), [
            '_page_save' => '1',
            'name' => 'Browser Expo',
            'city' => 'Mumbai',
            'country' => 'India',
            'year' => '',
            'description' => 'Test',
            'gallery_managed' => '1',
            'sort_order' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $this->assertNotNull(Exhibition::query()->where('slug', 'browser-expo')->first());
    }

    public function test_update_with_empty_year_preserves_exhibition(): void
    {
        $admin = User::factory()->admin()->create();
        $exhibition = Exhibition::query()->create([
            'slug' => 'old-expo',
            'name' => 'Old Expo',
            'year' => 2023,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'Old Expo Renamed',
            'year' => '',
            'gallery_managed' => '1',
            'sort_order' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.exhibitions.index'));

        $exhibition->refresh();
        $this->assertSame('Old Expo Renamed', $exhibition->name);
    }
}
