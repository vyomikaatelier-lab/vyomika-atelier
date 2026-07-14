<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::where('is_active', true)
            ->where('is_featured', true)
            ->latest()
            ->take(6)
            ->get();

        $categories = Category::where('is_active', true)->get();

        $featuredServices = Schema::hasTable('services')
            ? Service::where('is_active', true)->latest()->take(6)->get()
            : collect();

        $featuredProjects = Schema::hasTable('projects')
            ? Project::where('is_active', true)->where('is_featured', true)->latest('completed_at')->take(4)->get()
            : collect();

        $latestPosts = Schema::hasTable('blog_posts')
            ? BlogPost::where('is_active', true)->whereNotNull('published_at')->latest('published_at')->take(3)->get()
            : collect();

        return view('home', compact(
            'featuredProducts',
            'categories',
            'featuredServices',
            'featuredProjects',
            'latestPosts'
        ));
    }
}
