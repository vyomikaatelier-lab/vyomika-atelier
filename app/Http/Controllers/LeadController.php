<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function create()
    {
        return view('leads.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'budget' => 'nullable|string|max:100',
            'preferred_contact' => 'nullable|in:email,phone,whatsapp',
        ]);

        $lead = Lead::create([
            ...$validated,
            'type' => 'custom_order',
            'status' => 'new',
        ]);

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            Mail::raw(
                "New custom order request from {$lead->name} ({$lead->email}).\n\n{$lead->message}",
                fn ($message) => $message->to($adminEmail)->subject('New Custom Order Request')
            );
        }

        return back()->with('success', 'Thank you! We received your custom order request and will contact you soon.');
    }
}
