<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\MediaReferenceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * H4 — media deletion must never remove an in-use asset, must compare
 * complete normalized paths (no substring matches), must work without a
 * media_files row, must never treat remote URLs as local files, and must
 * fail closed when references cannot be determined.
 */
class MediaReferenceProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function scanner(): MediaReferenceScanner
    {
        return app(MediaReferenceScanner::class);
    }

    private function mediaRow(string $path, string $disk = 'public'): MediaFile
    {
        Storage::disk($disk)->put($path, 'x');

        return MediaFile::query()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => basename($path),
            'mime' => 'image/jpeg',
            'size' => 1,
            'is_private' => $disk !== 'public',
        ]);
    }

    /** Trait harness so deleteStoredPath() can be exercised directly. */
    private function deleteStoredPathViaTrait(?string $path): void
    {
        $harness = new class
        {
            use \App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;

            public function run(?string $path): void
            {
                $this->deleteStoredPath($path);
            }
        };

        $harness->run($path);
        $this->app->terminate();
    }

    private function shopProduct(array $overrides = []): Product
    {
        $category = Category::factory()->create(['section' => Product::SECTION_SHOP]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Reference family coverage
    // ------------------------------------------------------------------

    public function test_detects_product_image_gallery_and_og_image_references(): void
    {
        $this->shopProduct([
            'image' => 'products/main.jpg',
            'gallery' => ['products/gallery-one.jpg', 'products/gallery-two.jpg'],
            'og_image' => 'products/og.jpg',
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('products/main.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('products/gallery-one.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('products/gallery-two.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('products/og.jpg'));
        $this->assertSame(0, $this->scanner()->referenceCount('products/unused.jpg'));
    }

    public function test_detects_category_image_and_og_image_references(): void
    {
        Category::factory()->create([
            'image' => 'categories/cat.jpg',
            'og_image' => 'categories/cat-og.jpg',
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('categories/cat.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('categories/cat-og.jpg'));
    }

    public function test_detects_project_image_path_reference(): void
    {
        Project::query()->create([
            'project_name' => 'Lobby Screen',
            'image_path' => 'projects/lobby.jpg',
            'is_active' => true,
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('projects/lobby.jpg'));
    }

    public function test_detects_service_and_service_design_image_references(): void
    {
        $service = Service::query()->create([
            'name' => 'Partitions',
            'slug' => 'partitions-media-test',
            'image' => 'services/partitions.jpg',
            'is_active' => true,
        ]);

        ServiceDesign::query()->create([
            'service_id' => $service->id,
            'name' => 'Wave',
            'slug' => 'wave-media-test',
            'image' => 'service-designs/wave.jpg',
            'is_active' => true,
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('services/partitions.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('service-designs/wave.jpg'));
    }

    public function test_detects_blog_image_gallery_and_og_image_references(): void
    {
        BlogPost::query()->create([
            'title' => 'Media Post',
            'slug' => 'media-post',
            'content' => '<p>Body</p>',
            'image' => 'blog/hero.jpg',
            'og_image' => 'blog/og.jpg',
            'gallery' => ['blog/gallery-a.jpg'],
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('blog/hero.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('blog/og.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('blog/gallery-a.jpg'));
    }

    public function test_detects_exhibition_cover_and_gallery_references(): void
    {
        Exhibition::query()->create([
            'slug' => 'index-2026',
            'name' => 'INDEX 2026',
            'cover_image' => 'exhibitions/cover.jpg',
            'gallery' => ['exhibitions/booth-a.jpg', 'exhibitions/booth-b.jpg'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('exhibitions/cover.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('exhibitions/booth-a.jpg'));
        $this->assertSame(1, $this->scanner()->referenceCount('exhibitions/booth-b.jpg'));
    }

    public function test_detects_site_setting_json_references_across_families(): void
    {
        SiteSetting::setValue('landing_pages', [
            'corten-steel' => ['hero' => ['image' => 'landing/corten-hero.jpg']],
        ]);
        SiteSetting::setValue('collection_pages', [
            'coffee-tables' => ['hero' => ['image' => 'collections/coffee-hero.jpg']],
        ]);
        SiteSetting::setValue('page_heroes', [
            'about' => ['image' => 'heroes/about.jpg', 'image_mobile' => 'heroes/about-mobile.jpg'],
        ]);
        SiteSetting::setValue('service_page_heroes', [
            'partitions' => ['image' => 'heroes/partitions.jpg'],
        ]);
        SiteSetting::setValue('hero', [
            'slides' => [['image' => 'homepage/slide-one.jpg']],
        ]);
        SiteSetting::setValue('homepage', [
            'sections' => [['image' => 'homepage/section.jpg']],
        ]);
        SiteSetting::setValue('finish_swatches', [
            'gold-mirror' => 'finishes/gold-mirror.jpg',
        ]);
        SiteSetting::setValue('static_pages', [
            'about' => ['og_image' => 'static/about-og.jpg'],
        ]);

        foreach ([
            'landing/corten-hero.jpg',
            'collections/coffee-hero.jpg',
            'heroes/about.jpg',
            'heroes/about-mobile.jpg',
            'heroes/partitions.jpg',
            'homepage/slide-one.jpg',
            'homepage/section.jpg',
            'finishes/gold-mirror.jpg',
            'static/about-og.jpg',
        ] as $path) {
            $this->assertSame(1, $this->scanner()->referenceCount($path), "Expected {$path} to be detected");
        }
    }

    // ------------------------------------------------------------------
    // Matching safety
    // ------------------------------------------------------------------

    public function test_prefix_and_substring_collisions_are_not_matches(): void
    {
        $this->shopProduct([
            'image' => 'media/in-use.jpg.webp',
            'gallery' => ['other/media/in-use.jpg', 'media/xin-use.jpg'],
        ]);

        // The old LIKE '%path%' / str_contains logic would have counted these.
        $this->assertSame(0, $this->scanner()->referenceCount('media/in-use.jpg'));
        $this->assertSame(0, $this->scanner()->referenceCount('media/in-use'));
    }

    public function test_leading_slash_and_storage_prefix_variants_all_match(): void
    {
        $this->shopProduct(['image' => '/storage/media/variant.jpg']);
        Category::factory()->create(['image' => 'storage/media/variant.jpg']);
        Project::query()->create([
            'project_name' => 'Variant',
            'image_path' => '/media/variant.jpg',
            'is_active' => true,
        ]);

        // All three stored variants refer to the same local file.
        $this->assertSame(3, $this->scanner()->referenceCount('media/variant.jpg'));
        $this->assertSame(3, $this->scanner()->referenceCount('/storage/media/variant.jpg'));
        $this->assertSame(3, $this->scanner()->referenceCount('storage/media/variant.jpg'));
    }

    public function test_nested_json_values_are_compared_as_complete_strings(): void
    {
        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'hero' => ['image' => 'landing/railings-hero.jpg'],
                'cards' => [['image' => 'landing/card-one.jpg']],
            ],
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('landing/card-one.jpg'));
        // Substring of a stored JSON value must not match.
        $this->assertSame(0, $this->scanner()->referenceCount('landing/card-one'));
        $this->assertSame(0, $this->scanner()->referenceCount('landing/card-one.jpg.bak'));
    }

    public function test_foreign_remote_urls_are_never_counted_as_local_references(): void
    {
        $this->shopProduct([
            'image' => 'https://cdn.example.com/media/shared.jpg',
        ]);

        $this->assertSame(0, $this->scanner()->referenceCount('media/shared.jpg'));
    }

    public function test_same_host_absolute_url_counts_as_local_reference(): void
    {
        $host = rtrim((string) config('app.url'), '/');
        $this->shopProduct([
            'image' => $host.'/storage/media/self-hosted.jpg',
        ]);

        $this->assertSame(1, $this->scanner()->referenceCount('media/self-hosted.jpg'));
    }

    public function test_scanner_refuses_remote_and_invalid_targets(): void
    {
        foreach ([
            'https://example.com/media/file.jpg',
            'http://example.com/media/file.jpg',
            '//example.com/media/file.jpg',
            '   ',
            '../secrets/file.jpg',
        ] as $target) {
            try {
                $this->scanner()->referenceCount($target);
                $this->fail("Expected scanner to refuse target: {$target}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Cannot determine media references', $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------------
    // Deletion behavior
    // ------------------------------------------------------------------

    public function test_referenced_media_row_cannot_be_deleted_via_admin_route(): void
    {
        $media = $this->mediaRow('media/in-use.jpg');
        $this->shopProduct(['gallery' => ['/storage/media/in-use.jpg']]);

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, MediaFile::query()->count());
        Storage::disk('public')->assertExists('media/in-use.jpg');
    }

    public function test_unreferenced_media_row_is_deleted_via_admin_route(): void
    {
        $media = $this->mediaRow('media/unused.jpg');

        $this->actingAsAdmin($this->admin())
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MediaFile::query()->count());
        Storage::disk('public')->assertMissing('media/unused.jpg');
    }

    public function test_reference_check_blocks_disk_deletion_even_without_media_row(): void
    {
        Storage::disk('public')->put('media/no-row.jpg', 'x');
        $this->shopProduct(['image' => 'media/no-row.jpg']);
        $this->assertSame(0, MediaFile::query()->count());

        $this->deleteStoredPathViaTrait('media/no-row.jpg');

        Storage::disk('public')->assertExists('media/no-row.jpg');
    }

    public function test_truly_unreferenced_local_file_is_deleted_without_media_row(): void
    {
        Storage::disk('public')->put('media/orphan.jpg', 'x');

        $this->deleteStoredPathViaTrait('media/orphan.jpg');

        Storage::disk('public')->assertMissing('media/orphan.jpg');
    }

    public function test_similarly_prefixed_file_is_not_protected_by_unrelated_reference(): void
    {
        Storage::disk('public')->put('media/report.jpg', 'x');
        // References a longer, different filename that contains the target.
        $this->shopProduct(['image' => 'media/report.jpg.webp']);

        $this->deleteStoredPathViaTrait('media/report.jpg');

        Storage::disk('public')->assertMissing('media/report.jpg');
    }

    public function test_remote_urls_are_never_deleted_as_local_files(): void
    {
        Storage::disk('public')->put('media/twin.jpg', 'x');

        $this->deleteStoredPathViaTrait('https://cdn.example.com/media/twin.jpg');
        $this->deleteStoredPathViaTrait('//cdn.example.com/media/twin.jpg');
        $this->deleteStoredPathViaTrait('http://cdn.example.com/media/twin.jpg');

        Storage::disk('public')->assertExists('media/twin.jpg');
    }

    public function test_storage_prefixed_target_deletes_the_bare_disk_path(): void
    {
        Storage::disk('public')->put('media/prefixed.jpg', 'x');

        $this->deleteStoredPathViaTrait('/storage/media/prefixed.jpg');

        Storage::disk('public')->assertMissing('media/prefixed.jpg');
    }

    // ------------------------------------------------------------------
    // Fail-closed behavior
    // ------------------------------------------------------------------

    public function test_admin_route_refuses_deletion_when_reference_scan_fails(): void
    {
        $media = $this->mediaRow('media/scan-fails.jpg');

        $this->bindFailingScanner();

        $this->actingAsAdmin($this->admin())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, MediaFile::query()->count());
        Storage::disk('public')->assertExists('media/scan-fails.jpg');
    }

    public function test_delete_stored_path_fails_closed_when_scan_throws(): void
    {
        Storage::disk('public')->put('media/protected.jpg', 'x');

        $this->bindFailingScanner();

        $this->deleteStoredPathViaTrait('media/protected.jpg');

        Storage::disk('public')->assertExists('media/protected.jpg');
    }

    public function test_media_file_reference_count_fails_closed_on_invalid_state(): void
    {
        $media = new MediaFile([
            'disk' => 'public',
            'path' => 'https://example.com/not-local.jpg',
        ]);

        // Reference determination cannot prove safety — treated as referenced.
        $this->assertGreaterThan(0, $media->referenceCount());
    }

    private function bindFailingScanner(): void
    {
        $this->app->bind(MediaReferenceScanner::class, fn () => new class extends MediaReferenceScanner
        {
            public function referenceCount(string $path): int
            {
                throw new \RuntimeException('reference scan unavailable');
            }
        });
    }
}
