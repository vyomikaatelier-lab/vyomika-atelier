<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = MediaFile::query()->latest();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('filename', 'like', "%{$q}%");
        }

        if ($request->filled('type')) {
            if ($request->type === 'image') {
                $query->where('mime', 'like', 'image/%');
            } elseif ($request->type === 'pdf') {
                $query->where('mime', 'application/pdf');
            } elseif ($request->type === 'private') {
                $query->where('is_private', true);
            }
        }

        $media = $query->paginate(24)->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'is_private' => 'nullable|boolean',
            'alt' => 'nullable|string|max:255',
        ]);

        $private = $request->boolean('is_private');
        $path = $this->storeUpload($request, 'file', $private ? 'private-documents' : 'media', $private);

        if ($path) {
            MediaFile::query()->where('path', $path)->update([
                'alt' => $request->input('alt'),
                'is_private' => $private,
            ]);
        }

        return back()->with('success', 'File uploaded.');
    }

    public function destroy(MediaFile $medium)
    {
        if ($medium->referenceCount() > 0) {
            return back()->withErrors(['delete' => 'This file is referenced elsewhere and cannot be deleted.']);
        }

        $this->deleteStoredPath($medium->path);
        $medium->delete();

        return back()->with('success', 'File deleted.');
    }

    public function download(MediaFile $medium): StreamedResponse
    {
        abort_unless(Storage::disk($medium->disk)->exists($medium->path), 404);

        if ($medium->is_private) {
            return Storage::disk($medium->disk)->download($medium->path, $medium->filename);
        }

        return Storage::disk($medium->disk)->download($medium->path, $medium->filename);
    }
}
