<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin forms derive the URL slug from the name/title field. The derived value
 * must be validated against the unique index before the write, otherwise the
 * save blows up with a database integrity error instead of a form error.
 */
class AdminSlugCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function shopCategory(): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );
    }

    public function test_category_create_with_duplicate_name_returns_validation_error(): void
    {
        Category::query()->create([
            'name' => 'Terrazzo Tops',
            'slug' => 'terrazzo-tops',
            'section' => 'shop',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                '_page_save' => '1',
                'name' => 'Terrazzo Tops',
                'section' => 'shop',
            ])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::query()->where('slug', 'terrazzo-tops')->count());
    }

    public function test_category_rename_onto_existing_slug_returns_validation_error(): void
    {
        Category::query()->create([
            'name' => 'Terrazzo Tops',
            'slug' => 'terrazzo-tops',
            'section' => 'shop',
            'is_active' => true,
        ]);

        $other = Category::query()->create([
            'name' => 'Cane Screens',
            'slug' => 'cane-screens',
            'section' => 'shop',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.categories.edit', $other))
            ->put(route('admin.categories.update', $other), [
                '_page_save' => '1',
                'name' => 'Terrazzo Tops',
                'section' => 'shop',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.edit', $other))
            ->assertSessionHasErrors('name');

        $this->assertSame('cane-screens', $other->fresh()->slug);
    }

    public function test_category_can_still_save_without_renaming(): void
    {
        $category = Category::query()->create([
            'name' => 'Terrazzo Tops',
            'slug' => 'terrazzo-tops',
            'section' => 'shop',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.categories.update', $category), [
                '_page_save' => '1',
                'name' => 'Terrazzo Tops',
                'section' => 'shop',
                'is_active' => '1',
                'description' => 'Updated copy',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated copy', $category->fresh()->description);
    }

    public function test_blog_create_with_duplicate_title_returns_validation_error(): void
    {
        BlogPost::query()->create([
            'title' => 'Steel Trends',
            'slug' => 'steel-trends',
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.blog.create'))
            ->post(route('admin.blog.store'), [
                '_page_save' => '1',
                'title' => 'Steel Trends',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.blog.create'))
            ->assertSessionHasErrors('title');

        $this->assertSame(1, BlogPost::query()->where('slug', 'steel-trends')->count());
    }

    public function test_blog_rename_onto_existing_slug_returns_validation_error(): void
    {
        BlogPost::query()->create([
            'title' => 'Steel Trends',
            'slug' => 'steel-trends',
            'status' => 'published',
            'is_active' => true,
        ]);

        $post = BlogPost::query()->create([
            'title' => 'Brass Care',
            'slug' => 'brass-care',
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.blog.edit', $post))
            ->put(route('admin.blog.update', $post), [
                '_page_save' => '1',
                'title' => 'Steel Trends',
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.blog.edit', $post))
            ->assertSessionHasErrors('title');

        $this->assertSame('brass-care', $post->fresh()->slug);
    }

    public function test_product_create_with_duplicate_name_returns_validation_error(): void
    {
        $category = $this->shopCategory();

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Slim Partition',
            'slug' => 'slim-partition',
            'price' => 1000,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                '_page_save' => '1',
                'category_id' => $category->id,
                'name' => 'Slim Partition',
                'price' => 2000,
                'stock' => 3,
                'section' => Product::SECTION_STUDIO,
                'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
                'pricing_type' => Product::PRICING_SQUARE_FOOT,
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Product::query()->where('slug', 'slim-partition')->count());
    }

    public function test_project_create_with_duplicate_name_is_allowed(): void
    {
        Project::query()->create([
            'project_name' => 'Bandra Penthouse',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                '_page_save' => '1',
                'project_name' => 'Bandra Penthouse',
            ])
            ->assertRedirect(route('admin.projects.index'));

        $this->assertSame(2, Project::query()->where('project_name', 'Bandra Penthouse')->count());
    }

    public function test_service_create_with_duplicate_name_returns_validation_error(): void
    {
        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'pvd-partitions',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                '_page_save' => '1',
                'name' => 'PVD Partitions',
                'lead_form' => 'popup',
            ])
            ->assertRedirect(route('admin.services.create'))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Service::query()->where('slug', 'pvd-partitions')->count());
    }
}
