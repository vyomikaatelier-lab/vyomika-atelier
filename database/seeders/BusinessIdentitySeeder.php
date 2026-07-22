<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class BusinessIdentitySeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::setValue('brand', [
            'name' => 'Vyomika Atelier',
            'phone' => config('site.brand.phone', '+91 9205850254'),
            'email' => config('site.brand.email', 'namaste@vyomikaatelier.com'),
            'address_shop' => config('site.brand.address_shop', 'Pan-India fabrication & delivery'),
            'address_office' => config('site.brand.address_office'),
        ]);

        SiteSetting::setValue('business', config('legal.business', []));

        foreach (config('legal.pages', []) as $slug => $page) {
            LegalPage::query()->updateOrCreate(
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
