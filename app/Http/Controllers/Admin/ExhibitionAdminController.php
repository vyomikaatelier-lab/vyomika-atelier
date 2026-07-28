<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Admin\Concerns\ReordersRecords;
use App\Http\Controllers\Admin\Concerns\ResolvesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Exhibition;
use Illuminate\Http\Request;

class ExhibitionAdminController extends Controller
{
    use HandlesAdminUploads;
    use ReordersRecords;
    use ResolvesUniqueSlug;

    public function index()
    {
        $exhibitions = $this->ordered()->paginate(20);

        return view('admin.exhibitions.index', compact('exhibitions'));
    }

    public function create()
    {
        return view('admin.exhibitions.form');
    }

    public function store(Request $request)
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', $this->multipartPayloadErrorMessage());
        }

        $validated = $this->validateExhibition($request);
        $validated['slug'] = $this->resolveExhibitionSlug($validated['name']);
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['sort_order'] = $request->integer('sort_order', Exhibition::max('sort_order') + 1);
        $validated['cover_image'] = $this->resolveImageField($request, 'cover_file', 'cover_image', null, 'exhibitions');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', null, 'exhibitions');

        Exhibition::create($this->exhibitionPayload($validated));

        return redirect()->route('admin.exhibitions.index')->with('success', 'Exhibition created.');
    }

    public function edit(Exhibition $exhibition)
    {
        return view('admin.exhibitions.form', compact('exhibition'));
    }

    public function update(Request $request, Exhibition $exhibition)
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', $this->multipartPayloadErrorMessage());
        }

        $validated = $this->validateExhibition($request);
        $validated['slug'] = $this->resolveExhibitionSlug($validated['name'], $exhibition);
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['sort_order'] = $request->integer('sort_order', $exhibition->sort_order);
        $validated['cover_image'] = $this->resolveImageField($request, 'cover_file', 'cover_image', $exhibition->cover_image, 'exhibitions');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', $exhibition->gallery, 'exhibitions');

        $exhibition->update($this->exhibitionPayload($validated));

        return redirect()->route('admin.exhibitions.index')->with('success', 'Exhibition updated.');
    }

    public function destroy(Exhibition $exhibition)
    {
        $this->deleteStoredPath($exhibition->cover_image);

        foreach ($exhibition->gallery ?? [] as $path) {
            $this->deleteStoredPath($path);
        }

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

        $moved = $this->moveRecord(
            $this->ordered(),
            $this->ordered(),
            $exhibition,
            $direction
        );

        return back()->with(
            'success',
            $moved ? 'Exhibition order updated.' : 'Exhibition is already '.($direction === 'up' ? 'first' : 'last').'.'
        );
    }

    /**
     * The listing order the move buttons step through. The trailing key sort
     * keeps it total, so "one row up" is never ambiguous.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Exhibition>
     */
    private function ordered()
    {
        return Exhibition::query()
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->orderBy('id');
    }

    private function resolveExhibitionSlug(string $name, ?Exhibition $exhibition = null): string
    {
        return $this->resolveUniqueSlug(Exhibition::class, $name, 'name', $exhibition);
    }

    /** @param  array<string, mixed>  $validated */
    private function exhibitionPayload(array $validated): array
    {
        return collect($validated)->only((new Exhibition)->getFillable())->all();
    }

    private function validateExhibition(Request $request): array
    {
        $imageRules = $this->adminImageUploadRules();

        return $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'year' => 'nullable|integer|min:1990|max:2100',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:500',
            'cover_file' => $imageRules,
            'gallery_urls' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => $imageRules,
            'gallery_replace' => 'nullable|array',
            'gallery_replace.*' => $imageRules,
            'gallery_existing' => 'nullable|array',
            'gallery_existing.*' => 'string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ], $this->adminImageUploadMessages());
    }
}
