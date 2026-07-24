<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryAdminController extends Controller
{
    use HandlesAdminUploads;

    /** @var array<string, string> */
    private const SECTION_LABELS = [
        'shop' => 'Shop',
        'studio' => 'Studio',
        'railings' => 'Railings',
    ];

    public function index(Request $request)
    {
        $query = Category::query()->orderBy('sort_order')->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($request->filled('section')) {
            $query->where('section', $request->string('section'));
        }

        $categories = $query->withCount('products')->paginate(20)->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'sectionLabels' => self::SECTION_LABELS,
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', [
            'sectionLabels' => self::SECTION_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $request->integer('sort_order', Category::max('sort_order') + 1);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', null, 'categories');

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', [
            'category' => $category,
            'sectionLabels' => self::SECTION_LABELS,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $this->validateCategory($request, $category);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $request->integer('sort_order', $category->sort_order);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $category->image, 'categories');

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function sync(Request $request)
    {
        $synced = ProductCatalog::syncCanonicalCategories();
        $message = "Synced {$synced} canonical categories from catalog defaults.";

        if ($request->boolean('assign_products')) {
            $assigned = ProductCatalog::assignUnclassifiedProducts();
            $message .= " Reassigned or updated {$assigned} product(s).";
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', $message);
    }

    public function destroy(Request $request, Category $category)
    {
        if ($category->products()->exists()) {
            $request->validate([
                'reassign_category_id' => [
                    'required',
                    Rule::exists('categories', 'id')->whereNot('id', $category->id),
                ],
            ]);

            Product::query()
                ->where('category_id', $category->id)
                ->update(['category_id' => $request->integer('reassign_category_id')]);
        }

        $this->deleteStoredPath($category->image);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            Category::whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Category order updated.');
    }

    public function move(Category $category, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $neighbor = Category::query()
            ->when($direction === 'up', function ($query) use ($category) {
                $query->where('sort_order', '<', $category->sort_order)
                    ->orderByDesc('sort_order');
            }, function ($query) use ($category) {
                $query->where('sort_order', '>', $category->sort_order)
                    ->orderBy('sort_order');
            })
            ->first();

        if ($neighbor) {
            [$category->sort_order, $neighbor->sort_order] = [$neighbor->sort_order, $category->sort_order];
            $category->save();
            $neighbor->save();
        }

        return back()->with('success', 'Category order updated.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|in:shop,studio,railings',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);
    }
}
