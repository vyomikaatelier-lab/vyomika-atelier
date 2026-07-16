<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Exhibition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExhibitionAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $exhibitions = Exhibition::query()
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->paginate(20);

        return view('admin.exhibitions.index', compact('exhibitions'));
    }

    public function create()
    {
        return view('admin.exhibitions.form');
    }

    public function store(Request $request)
    {
        $validated = $this->validateExhibition($request);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $request->integer('sort_order', Exhibition::max('sort_order') + 1);
        $validated['cover_image'] = $this->resolveImageField($request, 'cover_file', 'cover_image', null, 'exhibitions');
        $validated['gallery'] = $this->parseMultilineUrls($request->input('gallery_urls'));

        Exhibition::create($validated);

        return redirect()->route('admin.exhibitions.index')->with('success', 'Exhibition created.');
    }

    public function edit(Exhibition $exhibition)
    {
        return view('admin.exhibitions.form', compact('exhibition'));
    }

    public function update(Request $request, Exhibition $exhibition)
    {
        $validated = $this->validateExhibition($request);
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $request->integer('sort_order', $exhibition->sort_order);
        $validated['cover_image'] = $this->resolveImageField($request, 'cover_file', 'cover_image', $exhibition->cover_image, 'exhibitions');
        $validated['gallery'] = $this->parseMultilineUrls($request->input('gallery_urls'));

        $exhibition->update($validated);

        return redirect()->route('admin.exhibitions.index')->with('success', 'Exhibition updated.');
    }

    public function destroy(Exhibition $exhibition)
    {
        $this->deleteStoredPath($exhibition->cover_image);
        $exhibition->delete();

        return redirect()->route('admin.exhibitions.index')->with('success', 'Exhibition deleted.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:exhibitions,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            Exhibition::whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Exhibition order updated.');
    }

    public function move(Exhibition $exhibition, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $neighbor = Exhibition::query()
            ->when($direction === 'up', function ($query) use ($exhibition) {
                $query->where('sort_order', '<', $exhibition->sort_order)
                    ->orderByDesc('sort_order');
            }, function ($query) use ($exhibition) {
                $query->where('sort_order', '>', $exhibition->sort_order)
                    ->orderBy('sort_order');
            })
            ->first();

        if ($neighbor) {
            [$exhibition->sort_order, $neighbor->sort_order] = [$neighbor->sort_order, $exhibition->sort_order];
            $exhibition->save();
            $neighbor->save();
        }

        return back()->with('success', 'Exhibition order updated.');
    }

    private function validateExhibition(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'year' => 'nullable|integer|min:1990|max:2100',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:500',
            'cover_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_urls' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
