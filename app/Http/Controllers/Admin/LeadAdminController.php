<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $leads = $query->paginate(15)->withQueryString();

        return view('admin.leads.index', compact('leads'));
    }

    public function show(Lead $lead)
    {
        return view('admin.leads.show', compact('lead'));
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', $lead->allowedStatuses()),
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $lead->update($validated);

        return back()->with('success', 'Lead updated.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted.');
    }

    public function downloadAttachment(Lead $lead): StreamedResponse
    {
        $path = $lead->attachmentPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $lead->attachmentFilename());
    }
}
