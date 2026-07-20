<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class ProfessionalApplicationAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()
            ->where('type', 'professional_application')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('protection_status')) {
            $query->where('protection_status', $request->protection_status);
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $applications = $query->paginate(15)->withQueryString();
        $statuses = Lead::applicationStatuses();

        return view('admin.professional-applications.index', compact('applications', 'statuses'));
    }

    public function show(Lead $professional_application)
    {
        abort_unless($professional_application->type === 'professional_application', 404);

        return view('admin.professional-applications.show', [
            'application' => $professional_application,
            'statuses' => Lead::applicationStatuses(),
        ]);
    }

    public function update(Request $request, Lead $professional_application)
    {
        abort_unless($professional_application->type === 'professional_application', 404);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Lead::applicationStatuses()),
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $professional_application->update($validated);

        return back()->with('success', 'Application updated.');
    }
}
