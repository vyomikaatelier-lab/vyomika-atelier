<?php

namespace App\Http\Controllers;

use App\Services\LeadProtectionService;
use Illuminate\Http\Request;

class VendorProposalController extends Controller
{
  public function __construct(
    private LeadProtectionService $leadProtection,
  ) {}

  public function show()
  {
    return view('vendor-proposal.index');
  }

    public function store(Request $request)
    {
        $request->merge(['enquiry_intent' => config('form_protection.vendor_intent')]);

        if ($response = $this->leadProtection->guard($request, 'vendor_proposal', false)) {
      return $response;
    }

    $validated = $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|max:255',
      'phone' => 'nullable|string|max:20',
      'company' => 'required|string|max:255',
      'message' => 'required|string|max:5000',
    ]);

    $result = $this->leadProtection->finalizeLead($request, 'vendor_proposal', [
      ...$validated,
      'type' => 'vendor_proposal',
      'subject' => 'Vendor / Service Proposal',
      'enquiry_intent' => config('form_protection.vendor_intent'),
      'status' => 'marketing_vendor',
      'metadata' => [
        'company' => $validated['company'],
      ],
    ]);

    $lead = $result['lead'];

    if ($result['notify']) {
      $this->leadProtection->notifyAdmin(
        $lead,
        "Vendor proposal from {$lead->name} ({$lead->email})\nCompany: {$validated['company']}\n\n{$lead->message}",
        'Vendor Proposal — Vyomika Atelier'
      );
    }

    return back()->with('success', $result['success_message']);
  }
}
