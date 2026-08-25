<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Support\BlogContent;
use App\Support\ProductPublicationPolicy;
use App\Support\Seo\BlogSeo;
use App\Support\Seo\StaticPageSeo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $activeCategory = $request->query('category', '');
        $search = trim((string) $request->query('q', ''));
        $categories = BlogContent::categoriesWithPublishedPosts();
        $featured = BlogContent::featuredPost($activeCategory !== '' ? $activeCategory : null);
        $posts = BlogContent::paginate(
            $activeCategory !== '' ? $activeCategory : null,
            $search !== '' ? $search : null,
            9,
            $featured && $activeCategory === '' && $search === '' ? $featured : null
        );
        $index = BlogContent::indexMeta();
        $pageSeo = StaticPageSeo::forSlug('blog');

        return view('blog.index', compact(
            'posts',
            'categories',
            'activeCategory',
            'search',
            'featured',
            'index',
            'pageSeo'
        ));
    }

    public function show(string $slug): View
    {
        $post = BlogContent::findBySlug($slug);

        abort_unless($post, 404);

        $relatedProducts = ProductPublicationPolicy::applyGalleryScope(
            Product::query()->whereIn('slug', $post->relatedProductSlugs())
        )->get();

        $relatedProjects = Project::query()
            ->whereIn('id', $post->relatedProjectIds())
            ->where('is_active', true)
            ->get();

        $relatedServices = Service::query()
            ->whereIn('slug', $post->relatedServiceSlugs())
            ->where('is_active', true)
            ->get();

        $relatedArticles = BlogContent::relatedPosts($post, 3);
        $adjacent = BlogContent::adjacentPosts($post);
        $pageSeo = BlogSeo::pageData($post);

        return view('blog.show', compact(
            'post',
            'relatedProducts',
            'relatedProjects',
            'relatedServices',
            'relatedArticles',
            'adjacent',
            'pageSeo'
        ));
    }
}
