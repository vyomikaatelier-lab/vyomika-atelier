<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class RailingQuoteAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()
            ->where('type', 'railing_quotation')
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

        $quotes = $query->paginate(15)->withQueryString();
        $statuses = Lead::quoteStatuses();

        return view('admin.railing-quotes.index', compact('quotes', 'statuses'));
    }

    public function show(Lead $railing_quote)
    {
        abort_unless($railing_quote->type === 'railing_quotation', 404);

        return view('admin.railing-quotes.show', [
            'quote' => $railing_quote,
            'statuses' => Lead::quoteStatuses(),
        ]);
    }

    public function update(Request $request, Lead $railing_quote)
    {
        abort_unless($railing_quote->type === 'railing_quotation', 404);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Lead::quoteStatuses()),
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $railing_quote->update($validated);

        return back()->with('success', 'Railing quote updated.');
    }
}
