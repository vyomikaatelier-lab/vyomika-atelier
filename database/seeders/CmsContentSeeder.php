<?php

namespace Database\Seeders;

use App\Models\Exhibition;
use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class CmsContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedExhibitions();
        $this->seedLegalPages();
    }

    private function seedExhibitions(): void
    {
        $events = config('about.exhibitions.events', []);

        foreach ($events as $index => $event) {
            Exhibition::query()->firstOrCreate(
                ['slug' => $event['slug']],
                [
                    'name' => $event['name'],
                    'city' => $event['location'] ?? null,
                    'country' => str_contains(strtolower($event['location'] ?? ''), 'london') ? 'United Kingdom' : 'India',
                    'year' => $event['year'] ?? null,
                    'description' => $event['summary'] ?? null,
                    'cover_image' => $event['images'][0] ?? null,
                    'gallery' => $event['images'] ?? [],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedLegalPages(): void
    {
        $pages = config('legal.pages', []);

        foreach ($pages as $slug => $page) {
            LegalPage::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $page['title'] ?? ucfirst($slug),
                    'meta_title' => $page['meta_title'] ?? null,
                    'meta_description' => $page['meta_description'] ?? null,
                    'sections' => $page['sections'] ?? [],
                    'content_updated_at' => now()->parse(config('legal.last_updated', now()))->toDateString(),
                ]
            );
        }
    }
}
