<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Schema::hasTable('products')
            ? \App\Support\ShopCatalog::applyShopScope(
                Product::where('is_active', true)->where('is_featured', true)
            )->latest()->take(6)->get()
            : collect();

        $categories = Schema::hasTable('categories')
            ? Category::where('is_active', true)->get()
            : collect();

        $featuredServices = Schema::hasTable('services')
            ? Service::where('is_active', true)->latest()->take(6)->get()
            : collect();

        $featuredProjects = Schema::hasTable('projects')
            ? Project::where('is_active', true)->where('is_featured', true)->latest('completed_at')->take(4)->get()
            : collect();

        $latestPosts = Schema::hasTable('blog_posts')
            ? BlogPost::where('is_active', true)->whereNotNull('published_at')->latest('published_at')->take(3)->get()
            : collect();

        $site = SiteContent::get();

        $portfolio = $featuredProjects->isNotEmpty()
            ? $featuredProjects
            : collect($site['portfolio'] ?? []);

        $services = $featuredServices->isNotEmpty()
            ? $featuredServices
            : collect($site['services'] ?? []);

        $shopItems = $featuredProducts->isNotEmpty()
            ? $featuredProducts
            : collect($site['shop'] ?? []);

        $blogItems = $latestPosts->isNotEmpty()
            ? $latestPosts
            : collect($site['blog']['posts'] ?? []);


        $trendingSlugs = collect($site['trending']['products'] ?? [])->pluck('slug')->filter();
        $trendingFromDb = ($trendingSlugs->isNotEmpty() && Schema::hasTable('products'))
            ? \App\Support\ShopCatalog::applyShopScope(
                Product::where('is_active', true)->whereIn('slug', $trendingSlugs)
            )->get()
            : collect();
        if ($trendingFromDb->count() < 4 && Schema::hasTable('products')) {
            $trendingFromDb = $trendingFromDb->concat(
                \App\Support\ShopCatalog::applyShopScope(
                    Product::where('is_active', true)
                        ->whereNotIn('id', $trendingFromDb->pluck('id'))
                )->latest()->take(4 - $trendingFromDb->count())->get()
            );
        }

        return view('home', compact(
            'featuredProducts',
            'categories',
            'featuredServices',
            'featuredProjects',
            'latestPosts',
            'site',
            'portfolio',
            'services',
            'shopItems',
            'blogItems',
            'trendingFromDb',
        ));
    }
}
