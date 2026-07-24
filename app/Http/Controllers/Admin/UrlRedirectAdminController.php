<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UrlRedirect;
use Illuminate\Http\Request;

class UrlRedirectAdminController extends Controller
{
    public function index()
    {
        $redirects = UrlRedirect::query()->orderBy('from_path')->paginate(50);

        return view('admin.redirects.index', compact('redirects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_path' => 'required|string|max:500',
            'to_url' => 'required|string|max:1000',
            'status_code' => 'nullable|integer|in:301,302,307,308',
            'is_active' => 'nullable|boolean',
        ]);

        UrlRedirect::query()->updateOrCreate(
            ['from_path' => UrlRedirect::normalizePath($validated['from_path'])],
            [
                'to_url' => $validated['to_url'],
                'status_code' => $validated['status_code'] ?? 301,
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        return back()->with('success', 'Redirect saved.');
    }

    public function destroy(UrlRedirect $redirect)
    {
        $redirect->delete();

        return back()->with('success', 'Redirect removed.');
    }
}
