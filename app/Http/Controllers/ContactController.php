<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadProtectionService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private LeadProtectionService $leadProtection,
    ) {}

    public function index()
    {
        return view('contact.index');
    }

    public function store(Request $request)
    {
        if ($response = $this->leadProtection->guard($request, 'contact')) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'enquiry_intent' => 'required|string|in:' . implode(',', array_keys(config('form_protection.enquiry_intents', []))),
        ]);

        $result = $this->leadProtection->finalizeLead($request, 'contact', [
            ...$validated,
            'type' => 'contact',
            'status' => 'new',
        ]);

        $lead = $result['lead'];

        if ($result['notify']) {
            $this->leadProtection->notifyAdmin(
                $lead,
                "Contact form: {$lead->subject}\nFrom: {$lead->name} ({$lead->email})\n\n{$lead->message}",
                "Contact: {$lead->subject}"
            );
        }

        return back()->with('success', $result['success_message']);
    }
}
