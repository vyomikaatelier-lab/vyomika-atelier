<?php

namespace App\Http\Controllers;

use App\Services\LeadProtectionService;
use Illuminate\Http\Request;

class DealerApplicationController extends Controller
{
  public function __construct(
    private LeadProtectionService $leadProtection,
  ) {}

  public function show()
  {
    return view('dealer.index');
  }

  public function store(Request $request)
  {
    if ($response = $this->leadProtection->guard($request, 'dealer_application')) {
      return $response;
    }

    $validated = $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|max:255',
      'phone' => 'required|string|max:20',
      'company' => 'required|string|max:255',
      'city' => 'required|string|max:120',
      'gst_number' => 'nullable|string|max:50',
      'years_in_business' => 'nullable|string|max:50',
      'message' => 'required|string|max:5000',
      'enquiry_intent' => 'required|string|in:' . implode(',', array_keys(config('form_protection.enquiry_intents', []))),
    ]);

    $header = collect([
      'Company' => $validated['company'],
      'City' => $validated['city'],
      'GST' => $validated['gst_number'] ?? null,
      'Years in business' => $validated['years_in_business'] ?? null,
    ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

    $result = $this->leadProtection->finalizeLead($request, 'dealer_application', [
      'name' => $validated['name'],
      'email' => $validated['email'],
      'phone' => $validated['phone'],
      'type' => 'dealer_application',
      'subject' => 'Dealer / B2B Application',
      'message' => $header . "\n\n---\n\n" . $validated['message'],
      'status' => 'unverified',
      'metadata' => [
        'company' => $validated['company'],
        'city' => $validated['city'],
        'gst_number' => $validated['gst_number'] ?? null,
        'years_in_business' => $validated['years_in_business'] ?? null,
        'project_location' => $validated['city'],
      ],
      'project_location' => $validated['city'],
    ]);

    $lead = $result['lead'];

    if ($result['notify']) {
      $this->leadProtection->notifyAdmin(
        $lead,
        "Dealer application from {$lead->name} ({$lead->email})\n\n{$lead->message}",
        'Dealer Application — Vyomika Atelier'
      );
    }

    return back()->with('success', $result['success_message']);
  }
}
