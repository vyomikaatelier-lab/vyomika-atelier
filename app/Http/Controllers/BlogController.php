<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Project;
use App\Support\BlogContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $activeCategory = $request->query('category', '');
        $categories = BlogContent::categories();
        $featured = $activeCategory === '' ? BlogContent::featuredPost() : null;
        $posts = BlogContent::paginate(
            $activeCategory !== '' ? $activeCategory : null,
            9,
            $featured
        );
        $index = BlogContent::indexMeta();

        return view('blog.index', compact('posts', 'categories', 'activeCategory', 'featured', 'index'));
    }

    public function show(string $slug): View
    {
        $post = BlogContent::findBySlug($slug);

        abort_unless($post, 404);

        $relatedProducts = Product::query()
            ->whereIn('slug', $post->relatedProductSlugs())
            ->where('is_active', true)
            ->get();

        $relatedProjects = Project::query()
            ->whereIn('slug', $post->relatedProjectSlugs())
            ->where('is_active', true)
            ->get();

        $relatedArticles = BlogContent::relatedPosts($post, 3);

        return view('blog.show', compact(
            'post',
            'relatedProducts',
            'relatedProjects',
            'relatedArticles'
        ));
    }
}
