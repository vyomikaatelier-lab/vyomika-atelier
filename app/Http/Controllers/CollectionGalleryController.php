<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CollectionGalleryController extends Controller
{
    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(config('collections', []));
    }

    public function index(string $slug): View|RedirectResponse
    {
        if ($slug === 'mirror-frames') {
            return redirect()->route('collections.mirror-frames.index');
        }

        abort_unless(in_array($slug, self::slugs(), true), 404);

        $page = config("collections.{$slug}");
        abort_unless(is_array($page), 404);

        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        abort_unless($category, 404);

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return view('collections.gallery.index', [
            'page' => $page,
            'slug' => $slug,
            'category' => $category,
            'products' => $products,
        ]);
    }
}
