<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Admin\Concerns\ReordersRecords;
use App\Http\Controllers\Admin\Concerns\ResolvesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryAdminController extends Controller
{
    use HandlesAdminUploads;
    use ReordersRecords;
    use ResolvesUniqueSlug;

    /** @var array<string, string> */
    private const SECTION_LABELS = [
        'shop' => 'Shop',
        'studio' => 'Studio',
        'railings' => 'Railings',
    ];

    public function index(Request $request)
    {
        $categories = $this->filtered($request)->withCount('products')->paginate(20)->withQueryString();

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
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the image only (max 4 MB).');
        }

        $validated = $this->validateCategory($request);
        $validated['slug'] = $this->resolveUniqueSlug(Category::class, $validated['name'], 'name');
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['hide_when_unavailable'] = $this->checkboxBoolean($request, 'hide_when_unavailable');
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
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload the image only (max 4 MB).');
        }

        $validated = $this->validateCategory($request, $category);
        $validated['slug'] = $this->resolveUniqueSlug(Category::class, $validated['name'], 'name', $category);
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['hide_when_unavailable'] = $this->checkboxBoolean($request, 'hide_when_unavailable');
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

    public function move(Request $request, Category $category, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        // The move form forwards the active filters so the neighbour is the row
        // the admin actually sees above or below this one.
        $moved = $this->moveRecord(
            $this->ordered(),
            $this->filtered($request),
            $category,
            $direction
        );

        return back()->with(
            'success',
            $moved ? 'Category order updated.' : 'Category is already '.($direction === 'up' ? 'first' : 'last').'.'
        );
    }

    /**
     * The listing order the move buttons step through. The trailing key sort
     * keeps it total, so "one row up" is never ambiguous.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Category>
     */
    private function ordered()
    {
        return Category::query()->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Category> */
    private function filtered(Request $request)
    {
        $query = $this->ordered();

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

        return $query;
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|in:shop,studio,railings',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);
    }
}
