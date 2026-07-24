<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Support\BlogContent;
use App\Support\MirrorFramesContent;
use App\Support\StorefrontRoutes;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        $static = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('shop.index'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('studio.index'), 'changefreq' => 'monthly', 'priority' => '0.85'],
            ['loc' => route('projects.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('blog.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => route('professionals.index'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => route('railings.index'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => route('corten-steel.show'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => route('shop.mirror-frames.index'), 'changefreq' => 'weekly', 'priority' => '0.85'],
            ['loc' => route('contact.index'), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];

        foreach ($static as $item) {
            $urls[] = $item;
        }

        foreach (StorefrontRoutes::shopCategorySlugs() as $shopSlug) {
            if ($shopSlug === 'mirror-frames') {
                continue;
            }

            $urls[] = [
                'loc' => route('shop.show', $shopSlug),
                'changefreq' => 'weekly',
                'priority' => '0.85',
            ];
        }

        foreach (StorefrontRoutes::studioUrlSlugs() as $studioSlug) {
            $urls[] = [
                'loc' => route('studio.show', $studioSlug),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }

        foreach (MirrorFramesContent::all()['designs'] ?? [] as $design) {
            if (empty($design['slug'])) {
                continue;
            }
            $urls[] = [
                'loc' => route('shop.mirror-frames.show', $design['slug']),
                'changefreq' => 'weekly',
                'priority' => '0.75',
            ];
        }

        foreach ([
            'legal.privacy',
            'legal.terms',
            'legal.shipping',
            'legal.cancellation',
            'legal.warranty',
            'legal.grievance',
        ] as $routeName) {
            $urls[] = [
                'loc' => route($routeName),
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ];
        }

        if (Schema::hasTable('blog_posts') && BlogContent::usesDatabase()) {
            BlogPost::query()
                ->where('is_active', true)
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get(['slug', 'published_at', 'updated_at'])
                ->each(function (BlogPost $post) use (&$urls) {
                    $urls[] = [
                        'loc' => route('blog.show', $post->slug),
                        'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ];
                });
        }

        if (Schema::hasTable('projects')) {
            Project::query()
                ->where('is_active', true)
                ->get(['slug', 'updated_at'])
                ->each(function (Project $project) use (&$urls) {
                    $urls[] = [
                        'loc' => route('projects.show', $project->slug),
                        'lastmod' => $project->updated_at?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ];
                });
        }

        if (Schema::hasTable('products')) {
            Product::query()
                ->where('is_active', true)
                ->where('section', Product::SECTION_SHOP)
                ->get(['slug', 'updated_at'])
                ->each(function (Product $product) use (&$urls) {
                    $urls[] = [
                        'loc' => route('shop.show', $product->slug),
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ];
                });
        }

        if (Schema::hasTable('services')) {
            Service::query()
                ->where('is_active', true)
                ->get(['slug', 'updated_at'])
                ->each(function (Service $service) use (&$urls) {
                    if (StorefrontRoutes::studioUrlForService($service->slug)) {
                        return;
                    }
                    if (in_array($service->slug, ['bespoke-metal-furniture', 'corten-steel-facade'], true)) {
                        return;
                    }
                    $urls[] = [
                        'loc' => route('services.show', $service->slug),
                        'lastmod' => $service->updated_at?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.7',
                    ];
                });
        }

        $xml = view('sitemap.xml', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
