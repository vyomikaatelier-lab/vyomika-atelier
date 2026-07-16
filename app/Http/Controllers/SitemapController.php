<?php



namespace App\Http\Controllers;



use App\Models\BlogPost;

use App\Models\Product;

use App\Models\Project;

use App\Models\Service;

use App\Support\BlogContent;
use App\Support\MirrorFramesContent;

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

            ['loc' => route('services.index'), 'changefreq' => 'monthly', 'priority' => '0.8'],

            ['loc' => route('projects.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],

            ['loc' => route('blog.index'), 'changefreq' => 'weekly', 'priority' => '0.8'],

            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.7'],

            ['loc' => route('professionals.index'), 'changefreq' => 'monthly', 'priority' => '0.7'],

            ['loc' => route('studio.railings'), 'changefreq' => 'monthly', 'priority' => '0.8'],

            ['loc' => route('collections.mirror-frames.index'), 'changefreq' => 'weekly', 'priority' => '0.85'],

            ['loc' => route('contact.index'), 'changefreq' => 'monthly', 'priority' => '0.7'],

            ['loc' => route('leads.create'), 'changefreq' => 'monthly', 'priority' => '0.6'],

            ['loc' => route('team'), 'changefreq' => 'yearly', 'priority' => '0.4'],

        ];



        foreach ($static as $item) {

            $urls[] = $item;

        }

        foreach (MirrorFramesContent::all()['designs'] ?? [] as $design) {
            $urls[] = [
                'loc' => route('collections.mirror-frames.show', $design['slug']),
                'changefreq' => 'weekly',
                'priority' => '0.75',
            ];
        }



        $legalRoutes = [

            'legal.privacy',

            'legal.terms',

            'legal.shipping',

            'legal.cancellation',

            'legal.warranty',

            'legal.grievance',

        ];



        foreach ($legalRoutes as $routeName) {

            $urls[] = [

                'loc' => route($routeName),

                'changefreq' => 'yearly',

                'priority' => '0.3',

            ];

        }



        if (Schema::hasTable('blog_posts')) {

            $posts = BlogContent::usesDatabase()

                ? BlogPost::query()->where('is_active', true)->whereNotNull('published_at')->get()

                : collect();



            if ($posts->isEmpty()) {

                $posts = BlogContent::allPosts();

            }



            foreach ($posts as $post) {

                $urls[] = [

                    'loc' => route('blog.show', $post->slug),

                    'lastmod' => $post->published_at?->toAtomString(),

                    'changefreq' => 'monthly',

                    'priority' => '0.6',

                ];

            }

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


