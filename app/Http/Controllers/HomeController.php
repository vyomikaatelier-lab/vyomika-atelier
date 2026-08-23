<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Support\Seo\StaticPageSeo;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Schema::hasTable('products')
            ? \App\Support\ShopCatalog::applyListingScope(
                Product::where('is_active', true)
            )->orderedForDisplay()->take(6)->get()
            : collect();

        $categories = Schema::hasTable('categories')
            ? Category::where('is_active', true)->get()
            : collect();

        $featuredServices = Schema::hasTable('services')
            ? Service::where('is_active', true)->latest()->take(6)->get()
            : collect();

        $featuredProjects = Schema::hasTable('projects')
            ? Project::where('is_active', true)->orderBy('display_order')->take(4)->get()
            : collect();

        $latestPosts = Schema::hasTable('blog_posts')
            ? BlogPost::where('is_active', true)->whereNotNull('published_at')->latest('published_at')->take(3)->get()
            : collect();

        $site = SiteContent::get();

        $portfolio = $this->withConfigPreview($featuredProjects, Project::class, $site['portfolio'] ?? []);
        $services = $this->withConfigPreview($featuredServices, Service::class, $site['services'] ?? []);
        $shopItems = $this->withConfigPreview($featuredProducts, Product::class, $site['shop'] ?? []);
        $blogItems = $this->withConfigPreview($latestPosts, BlogPost::class, $site['blog']['posts'] ?? []);


        $trendingSlugs = collect($site['trending']['products'] ?? [])->pluck('slug')->filter();
        $trendingFromDb = ($trendingSlugs->isNotEmpty() && Schema::hasTable('products'))
            ? \App\Support\ShopCatalog::applyListingScope(
                Product::where('is_active', true)->whereIn('slug', $trendingSlugs)
            )->orderedForDisplay()->get()
            : collect();
        if ($trendingFromDb->count() < 4 && Schema::hasTable('products')) {
            $trendingFromDb = $trendingFromDb->concat(
                \App\Support\ShopCatalog::applyListingScope(
                    Product::where('is_active', true)
                        ->whereNotIn('id', $trendingFromDb->pluck('id'))
                )->orderedForDisplay()->take(4 - $trendingFromDb->count())->get()
            )->unique('id')->sortByDesc(fn (Product $product) => [$product->sort_order, $product->id])->values();
        }

        return view('home', [
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'featuredServices' => $featuredServices,
            'featuredProjects' => $featuredProjects,
            'latestPosts' => $latestPosts,
            'site' => $site,
            'portfolio' => $portfolio,
            'services' => $services,
            'shopItems' => $shopItems,
            'blogItems' => $blogItems,
            'trendingFromDb' => $trendingFromDb,
            'pageSeo' => StaticPageSeo::forSlug('home'),
        ]);
    }

    /**
     * Config seed content is only a first-deploy preview. Once the admin owns
     * rows in a table, an empty result is a deliberate editorial choice and must
     * not fall back to the hardcoded homepage sections.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $records
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<int, mixed>  $preview
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function withConfigPreview($records, string $model, array $preview)
    {
        if ($records->isNotEmpty()) {
            return $records;
        }

        $table = (new $model)->getTable();

        if (Schema::hasTable($table) && $model::query()->exists()) {
            return $records;
        }

        return collect($preview);
    }
}
